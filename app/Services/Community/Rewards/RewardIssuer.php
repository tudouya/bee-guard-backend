<?php

namespace App\Services\Community\Rewards;

use App\Enums\RewardIssuanceStatus;
use App\Models\CommunityPost;
use App\Models\CouponTemplate;
use App\Models\RewardIssuance;
use App\Models\RewardRule;
use App\Models\User;
use App\Notifications\RewardIssuedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RewardIssuer
{
    public function issueForPost(RewardRule $rule, CommunityPost $post): RewardIssuance
    {
        return DB::transaction(function () use ($rule, $post) {
            $rule = RewardRule::query()->lockForUpdate()->findOrFail($rule->getKey());
            $farmer = $post->author;

            if (! $farmer instanceof User) {
                throw new RuntimeException('Post author missing for reward issuance.');
            }

            $existing = RewardIssuance::query()
                ->where('reward_rule_id', $rule->getKey())
                ->where('community_post_id', $post->getKey())
                ->where('farmer_user_id', $farmer->getKey())
                ->first();

            if ($existing) {
                return $existing;
            }

            $template = $rule->couponTemplate;

            if ($template) {
                $this->guardTemplateAvailability($template);
            }

            $status = $rule->requiresManualFulfillment()
                ? RewardIssuanceStatus::PendingReview
                : RewardIssuanceStatus::Issued;

            $issuedAt = $status === RewardIssuanceStatus::Issued ? now() : null;

            $issuance = RewardIssuance::query()->create([
                'reward_rule_id' => $rule->getKey(),
                'coupon_template_id' => $template?->getKey(),
                'farmer_user_id' => $farmer->getKey(),
                'community_post_id' => $post->getKey(),
                'status' => $status,
                'issued_by' => null,
                'issued_at' => $issuedAt,
                'expires_at' => $template?->valid_until,
                'coupon_code' => null,
                'store_platform' => $template?->platform,
                'store_name' => $template?->store_name,
                'store_url' => $template?->store_url,
                'face_value' => $template?->face_value,
                'usage_instructions' => $template?->usage_instructions,
                'audit_log' => [],
            ]);

            $issuance->addAuditEntry(
                action: 'created',
                actorId: null,
                metadata: [
                    'mode' => $rule->fulfillment_mode->value,
                ]
            );

            if ($status === RewardIssuanceStatus::Issued) {
                $issuance->addAuditEntry('auto_issued');
                $issuance->save();
                $this->notifyIssued($issuance);
            } else {
                $issuance->save();
            }

            return $issuance;
        });
    }

    public function approveManual(RewardIssuance $issuance, User $issuer): RewardIssuance
    {
        if ($issuance->status !== RewardIssuanceStatus::PendingReview) {
            throw new RuntimeException('Reward issuance not pending manual review.');
        }

        DB::transaction(function () use (&$issuance, $issuer) {
            $issuance = RewardIssuance::query()->lockForUpdate()->findOrFail($issuance->getKey());

            if ($issuance->status !== RewardIssuanceStatus::PendingReview) {
                return;
            }

            $template = $issuance->couponTemplate;
            if ($template) {
                $this->guardTemplateAvailability($template);
            }

            $issuance->status = RewardIssuanceStatus::Issued;
            $issuance->issued_at = now();
            $issuance->issued_by = $issuer->getKey();
            $issuance->addAuditEntry('manual_approved', $issuer->getKey());
            $issuance->save();
            $this->notifyIssued($issuance);
        });

        return $issuance->fresh();
    }

    public function cancelPending(RewardIssuance $issuance, User $issuer, string $reason = ''): RewardIssuance
    {
        if ($issuance->status !== RewardIssuanceStatus::PendingReview) {
            throw new RuntimeException('Only pending rewards can be cancelled.');
        }

        DB::transaction(function () use (&$issuance, $issuer, $reason) {
            $issuance = RewardIssuance::query()->lockForUpdate()->findOrFail($issuance->getKey());

            if ($issuance->status !== RewardIssuanceStatus::PendingReview) {
                return;
            }

            $issuance->status = RewardIssuanceStatus::Cancelled;
            $issuance->addAuditEntry('manual_cancelled', $issuer->getKey(), ['reason' => $reason]);
            $issuance->save();
        });

        return $issuance->fresh();
    }

    protected function guardTemplateAvailability(CouponTemplate $template): void
    {
        if (is_null($template->total_quantity)) {
            return;
        }

        $used = RewardIssuance::query()
            ->where('coupon_template_id', $template->getKey())
            ->whereNotIn('status', [
                RewardIssuanceStatus::Cancelled->value,
            ])
            ->lockForUpdate()
            ->count();

        if ($used >= $template->total_quantity) {
            Log::warning('Coupon template stock exhausted', ['template_id' => $template->getKey()]);
            throw new RuntimeException('Coupon template has no remaining stock.');
        }
    }

    protected function notifyIssued(RewardIssuance $issuance): void
    {
        $issuance->loadMissing('farmer');
        $farmer = $issuance->farmer;

        if (! $farmer) {
            return;
        }

        $farmer->notify(new RewardIssuedNotification($issuance));
    }
}
