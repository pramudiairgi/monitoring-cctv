<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class CameraFactory extends Factory
{
    protected $model = \App\Models\Camera::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'stream_url' => fake()->url(),
            'category_id' => Category::factory(),
            'status' => fake()->randomElement(['online', 'offline']),
            'order' => fake()->numberBetween(0, 100),
        ];
    }
}
