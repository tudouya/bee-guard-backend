<?php

namespace App\Http\Controllers\Api;

use App\Enums\RewardIssuanceStatus;
use App\Http\Controllers\Controller;
use App\Models\RewardIssuance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RewardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $uiStatus = (string) $request->query('status');
        $perPage = (int) $request->integer('per_page', 10);
        $perPage = $perPage > 50 ? 50 : ($perPage <= 0 ? 10 : $perPage);

        $query = RewardIssuance::query()
            ->where('farmer_user_id', $user->id)
            ->with(['rewardRule.couponTemplate.enterprise', 'couponTemplate.enterprise', 'post'])
            ->orderByDesc('created_at');

        if ($uiStatus !== '') {
            $states = $this->mapUiStatusToDatabaseStatuses($uiStatus);
            if (! empty($states)) {
                $query->whereIn('status', $states);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $paginator = $query->paginate($perPage)->appends($request->query());

        $data = $paginator->getCollection()
            ->map(fn (RewardIssuance $issuance) => $this->transformIssuance($issuance))
            ->values();

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => $data,
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'total_pages' => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $issuance = RewardIssuance::query()
            ->where('farmer_user_id', $request->user()->id)
            ->with(['rewardRule.couponTemplate.enterprise', 'couponTemplate.enterprise', 'post'])
            ->find($id);

        if (! $issuance) {
            return response()->json(['code' => 404, 'message' => 'NOT_FOUND'], 404);
        }

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => $this->transformIssuance($issuance),
        ]);
    }

    public function acknowledge(Request $request, string $id): JsonResponse
    {
        $issuance = $this->findOwnedIssuance($request, $id);
        if (! $issuance) {
            return response()->json(['code' => 404, 'message' => 'NOT_FOUND'], 404);
        }

        $issuance->addAuditEntry('acknowledged', $request->user()->id);
        $issuance->save();

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => $this->transformIssuance($issuance->fresh(['rewardRule.couponTemplate.enterprise', 'couponTemplate.enterprise', 'post'])),
        ]);
    }

    public function markUsed(Request $request, string $id): JsonResponse
    {
        $issuance = $this->findOwnedIssuance($request, $id);
        if (! $issuance) {
            return response()->json(['code' => 404, 'message' => 'NOT_FOUND'], 404);
        }

        if ($issuance->status !== RewardIssuanceStatus::Issued) {
            return response()->json([
                'code' => 422,
                'message' => 'STATUS_INVALID',
                'errors' => [
                    'status' => ['只有已发放的奖励可以标记为已使用'],
                ],
            ], 422);
        }

        $issuance->status = RewardIssuanceStatus::Used;
        $issuance->addAuditEntry('marked_used', $request->user()->id);
        $issuance->save();

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => $this->transformIssuance($issuance->fresh(['rewardRule.couponTemplate.enterprise', 'couponTemplate.enterprise', 'post'])),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        $counts = RewardIssuance::query()
            ->select('status')
            ->selectRaw('COUNT(*) AS aggregate')
            ->where('farmer_user_id', $user->id)
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $result = [];
        foreach ($this->statusBuckets() as $uiStatus => $statuses) {
            $result[$uiStatus] = collect($statuses)
                ->map(fn (RewardIssuanceStatus $status) => (int) ($counts[$status->value] ?? 0))
                ->sum();
        }

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => $result,
        ]);
    }

    protected function transformIssuance(RewardIssuance $issuance): array
    {
        $rule = $issuance->rewardRule;
        $template = $issuance->couponTemplate;
        $enterprise = $template?->enterprise;

        return [
            'rewardId' => $issuance->getKey(),
            'rewardName' => $template?->title ?? $rule?->name ?? '奖励',
            'rewardType' => 'coupon',
            'status' => $this->mapDatabaseStatusToUi($issuance->status),
            'statusText' => $this->statusLabel($issuance->status),
            'amount' => $template?->face_value ? (float) $template->face_value : null,
            'unit' => $template?->face_value ? '元' : null,
            'source' => $enterprise?->name ?? '平台',
            'platform' => $this->platformLabel($template?->platform),
            'expireAt' => optional($issuance->expires_at)?->toDateString(),
            'issuedAt' => optional($issuance->issued_at)?->toDateString(),
            'usedAt' => $this->formatIsoToDate($this->extractAuditTimestamp($issuance, 'marked_used')),
            'usageLink' => $template?->store_url,
            'usageNotes' => $issuance->usage_instructions,
            'remainCount' => null,
            'relatedTitle' => $issuance->post?->title,
            'code' => $issuance->coupon_code,
            'extra' => $this->buildExtraPayload($issuance),
            'auditLog' => array_values($issuance->audit_log ?? []),
        ];
    }

    protected function statusLabel(RewardIssuanceStatus $status): string
    {
        return match ($status) {
            RewardIssuanceStatus::PendingReview => '待审核',
            RewardIssuanceStatus::Ready, RewardIssuanceStatus::Issued => '可使用',
            RewardIssuanceStatus::Used => '已使用',
            RewardIssuanceStatus::Expired, RewardIssuanceStatus::Cancelled => '已过期',
        };
    }

    protected function platformLabel(?string $platform): string
    {
        return match ($platform) {
            'jd' => '京东',
            'taobao' => '淘宝',
            'pinduoduo' => '拼多多',
            'offline' => '线下门店',
            'other' => '其他平台',
            null => '—',
            default => $platform,
        };
    }

    protected function extractAuditTimestamp(RewardIssuance $issuance, string $action): ?string
    {
        $entry = collect($issuance->audit_log ?? [])
            ->reverse()
            ->firstWhere('action', $action);

        if (! $entry) {
            return null;
        }

        return isset($entry['timestamp']) ? (string) $entry['timestamp'] : null;
    }

    protected function formatIsoToDate(?string $timestamp): ?string
    {
        if (! $timestamp) {
            return null;
        }

        try {
            return Carbon::parse($timestamp)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function buildExtraPayload(RewardIssuance $issuance): array
    {
        $extra = [];
        $rule = $issuance->rewardRule;
        if ($rule) {
            $extra['grantReason'] = $rule->name;
        }

        $latest = collect($issuance->audit_log ?? [])->last();
        if ($latest && ! empty($latest['metadata']['reason'])) {
            $extra['remark'] = (string) $latest['metadata']['reason'];
        }

        return $extra;
    }

    protected function statusBuckets(): array
    {
        return [
            'pending' => [RewardIssuanceStatus::PendingReview],
            'usable' => [RewardIssuanceStatus::Ready, RewardIssuanceStatus::Issued],
            'used' => [RewardIssuanceStatus::Used],
            'expired' => [RewardIssuanceStatus::Expired, RewardIssuanceStatus::Cancelled],
        ];
    }

    protected function mapUiStatusToDatabaseStatuses(string $uiStatus): array
    {
        return collect($this->statusBuckets()[$uiStatus] ?? [])
            ->map(fn (RewardIssuanceStatus $status) => $status->value)
            ->values()
            ->all();
    }

    protected function mapDatabaseStatusToUi(RewardIssuanceStatus $status): string
    {
        return match ($status) {
            RewardIssuanceStatus::PendingReview => 'pending',
            RewardIssuanceStatus::Ready, RewardIssuanceStatus::Issued => 'usable',
            RewardIssuanceStatus::Used => 'used',
            RewardIssuanceStatus::Expired, RewardIssuanceStatus::Cancelled => 'expired',
        };
    }

    protected function findOwnedIssuance(Request $request, string $id): ?RewardIssuance
    {
        return RewardIssuance::query()
            ->where('farmer_user_id', $request->user()->id)
            ->where('id', $id)
            ->first();
    }
}
