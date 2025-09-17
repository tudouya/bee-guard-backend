<?php

namespace App\Models;

use App\Enums\RewardComparator;
use App\Enums\RewardFulfillmentMode;
use App\Enums\RewardMetric;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RewardRule extends Model
{
    use HasFactory;
    use UsesUuid;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'metric',
        'comparator',
        'threshold',
        'fulfillment_mode',
        'coupon_template_id',
        'badge_type',
        'lecturer_program',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'metric' => RewardMetric::class,
            'comparator' => RewardComparator::class,
            'fulfillment_mode' => RewardFulfillmentMode::class,
            'threshold' => 'integer',
            'lecturer_program' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function couponTemplate(): BelongsTo
    {
        return $this->belongsTo(CouponTemplate::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function rewardIssuances(): HasMany
    {
        return $this->hasMany(RewardIssuance::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForMetric(Builder $query, RewardMetric $metric): Builder
    {
        return $query->where('metric', $metric->value);
    }

    public function requiresManualFulfillment(): bool
    {
        return $this->fulfillment_mode === RewardFulfillmentMode::Manual;
    }
}
