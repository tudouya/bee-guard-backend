<?php

namespace App\Models;

use App\Enums\RewardIssuanceStatus;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class RewardIssuance extends Model
{
    use HasFactory;
    use UsesUuid;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'reward_rule_id',
        'coupon_template_id',
        'farmer_user_id',
        'community_post_id',
        'status',
        'issued_by',
        'issued_at',
        'expires_at',
        'coupon_code',
        'store_platform',
        'store_name',
        'store_url',
        'face_value',
        'usage_instructions',
        'audit_log',
    ];

    protected function casts(): array
    {
        return [
            'status' => RewardIssuanceStatus::class,
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'face_value' => 'decimal:2',
            'audit_log' => 'array',
        ];
    }

    public function rewardRule(): BelongsTo
    {
        return $this->belongsTo(RewardRule::class);
    }

    public function couponTemplate(): BelongsTo
    {
        return $this->belongsTo(CouponTemplate::class);
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'farmer_user_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'community_post_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function scopeStatus(Builder $query, RewardIssuanceStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    public function addAuditEntry(string $action, ?int $actorId = null, array $metadata = []): void
    {
        $log = $this->audit_log ?? [];
        $log[] = [
            'action' => $action,
            'actor_id' => $actorId,
            'metadata' => $metadata,
            'timestamp' => Carbon::now()->toIso8601String(),
        ];

        $this->audit_log = $log;
    }
}
