<?php

namespace Database\Factories;

use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityPost>
 */
class CommunityPostFactory extends Factory
{
    protected $model = CommunityPost::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement(['question', 'experience']),
            'title' => $this->faker->sentence(6),
            'content' => $this->faker->paragraph(3),
            'content_format' => 'plain',
            'images' => [],
            'status' => 'pending',
            'views' => 0,
            'likes' => 0,
            'replies_count' => 0,
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
