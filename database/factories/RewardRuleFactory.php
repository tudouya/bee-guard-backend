<?php

namespace Database\Factories;

use App\Enums\RewardComparator;
use App\Enums\RewardFulfillmentMode;
use App\Enums\RewardMetric;
use App\Models\RewardRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RewardRule>
 */
class RewardRuleFactory extends Factory
{
    protected $model = RewardRule::class;

    public function definition(): array
    {
        return [
            'name' => '点赞激励规则',
            'metric' => RewardMetric::Likes,
            'comparator' => RewardComparator::GreaterThanOrEqual,
            'threshold' => 100,
            'fulfillment_mode' => RewardFulfillmentMode::Automatic,
            'coupon_template_id' => null,
            'badge_type' => null,
            'lecturer_program' => false,
            'is_active' => true,
            'created_by' => User::factory()->state(['role' => 'super_admin']),
            'updated_by' => User::factory()->state(['role' => 'super_admin']),
        ];
    }

    public function automatic(): self
    {
        return $this->state(fn () => [
            'fulfillment_mode' => RewardFulfillmentMode::Automatic,
        ]);
    }

    public function manual(): self
    {
        return $this->state(fn () => [
            'fulfillment_mode' => RewardFulfillmentMode::Manual,
        ]);
    }

    public function metric(RewardMetric $metric, int $threshold = 100): self
    {
        return $this->state(fn () => [
            'metric' => $metric,
            'threshold' => $threshold,
        ]);
    }
}
