<?php

namespace Database\Factories;

use App\Enums\CouponTemplateStatus;
use App\Models\CouponTemplate;
use App\Models\Enterprise;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<CouponTemplate>
 */
class CouponTemplateFactory extends Factory
{
    protected $model = CouponTemplate::class;

    public function definition(): array
    {
        $start = Carbon::now()->startOfDay();
        $end = $start->copy()->addMonth();

        return [
            'enterprise_id' => Enterprise::factory(),
            'title' => $this->faker->words(3, true) . ' 优惠券',
            'platform' => $this->faker->randomElement(['jd', 'taobao', 'pinduoduo', 'offline']),
            'store_name' => $this->faker->company(),
            'store_url' => $this->faker->url(),
            'face_value' => $this->faker->randomFloat(2, 5, 50),
            'total_quantity' => 100,
            'valid_from' => $start,
            'valid_until' => $end,
            'usage_instructions' => '凭券到店或在线下单可享折扣。',
            'status' => CouponTemplateStatus::Draft,
            'rejection_reason' => null,
            'submitted_by' => User::factory(),
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }

    public function approved(): self
    {
        return $this->state(fn () => [
            'status' => CouponTemplateStatus::Approved,
            'reviewed_by' => User::factory()->state(['role' => 'super_admin']),
            'reviewed_at' => Carbon::now(),
        ]);
    }

    public function status(CouponTemplateStatus $status): self
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
