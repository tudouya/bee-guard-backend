<?php

namespace Database\Factories;

use App\Models\CommunityPost;
use App\Models\CommunityPostReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityPostReply>
 */
class CommunityPostReplyFactory extends Factory
{
    protected $model = CommunityPostReply::class;

    public function definition(): array
    {
        return [
            'post_id' => CommunityPost::factory(),
            'user_id' => User::factory(),
            'content' => $this->faker->sentence(12),
            'status' => 'pending',
            'reply_type' => 'farmer',
        ];
    }

    public function approved(): self
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'published_at' => now(),
        ]);
    }
}
