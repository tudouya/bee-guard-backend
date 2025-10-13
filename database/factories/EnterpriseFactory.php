<?php

namespace Database\Factories;

use App\Models\Enterprise;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enterprise>
 */
class EnterpriseFactory extends Factory
{
    protected $model = Enterprise::class;

    public function definition(): array
    {
        return [
            'owner_user_id' => User::factory(),
            'name' => $this->faker->company(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => $this->faker->phoneNumber(),
            'status' => 'active',
            'intro' => $this->faker->sentence(12),
            'logo_url' => $this->faker->imageUrl(200, 200, 'business', true),
            'certifications' => '蜂业协会认证,ISO9001',
            'services' => '蜂病检测服务,疫病防控培训',
            'promotions' => '新客户下单立减,合作包年赠送检测次数',
            'contact_wechat' => 'BeeService_' . $this->faker->lexify('????'),
            'contact_link' => $this->faker->url(),
            'meta' => [],
        ];
    }
}
