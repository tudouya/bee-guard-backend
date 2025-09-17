<?php

namespace App\Notifications;

use App\Models\RewardIssuance;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RewardIssuedNotification extends Notification
{
    use Queueable;

    public function __construct(protected RewardIssuance $issuance)
    {
        $this->issuance->loadMissing(['rewardRule', 'couponTemplate']);
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $rule = $this->issuance->rewardRule;
        $template = $this->issuance->couponTemplate;

        return [
            'issuance_id' => $this->issuance->getKey(),
            'status' => $this->issuance->status->value,
            'reward_rule' => [
                'id' => $rule?->getKey(),
                'name' => $rule?->name,
                'metric' => $rule?->metric->value,
                'threshold' => $rule?->threshold,
            ],
            'coupon' => [
                'id' => $template?->getKey(),
                'title' => $template?->title,
                'platform' => $template?->platform,
                'store_name' => $template?->store_name,
                'store_url' => $template?->store_url,
                'face_value' => $template?->face_value,
                'expires_at' => optional($this->issuance->expires_at)->toIso8601String(),
            ],
        ];
    }
}
