<?php

namespace Database\Factories;

use App\Models\Camera;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class CameraFactory extends Factory
{
    protected $model = Camera::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'stream_url' => fake()->url(),
            'adaptive_url' => null,
            'target_url' => null,
            'category_id' => Category::factory(),
            'status' => fake()->randomElement(['online', 'offline']),
            'order' => fake()->numberBetween(0, 100),
            'maintenance' => false,
        ];
    }
}
