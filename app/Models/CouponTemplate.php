<?php

namespace App\Models;

use App\Enums\CouponTemplateStatus;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CouponTemplate extends Model
{
    use HasFactory;
    use UsesUuid;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'enterprise_id',
        'title',
        'platform',
        'store_name',
        'store_url',
        'face_value',
        'total_quantity',
        'valid_from',
        'valid_until',
        'usage_instructions',
        'status',
        'rejection_reason',
        'submitted_by',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CouponTemplateStatus::class,
            'face_value' => 'decimal:2',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function enterprise(): BelongsTo
    {
        return $this->belongsTo(Enterprise::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function rewardRules(): HasMany
    {
        return $this->hasMany(RewardRule::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', CouponTemplateStatus::Approved);
    }
}
