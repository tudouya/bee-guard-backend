<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Disease>
 */
class DiseaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->lexify('DS???')),
            'name' => $this->faker->unique()->words(2, true),
            'description' => $this->faker->paragraph(),
            'brief' => $this->faker->sentence(6),
            'symptom' => $this->faker->sentence(10),
            'transmit' => $this->faker->sentence(10),
            'prevention' => $this->faker->sentence(10),
            'status' => 'active',
            'sort' => 1,
        ];
    }
}
