<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'brand_name' => fake()->company(),
            'website' => fake()->url(),
            'description' => fake()->paragraph(),
            'segment' => fake()->word(),
            'logo_url' => fake()->imageUrl(),
            'competitors' => [
                fake()->url(),
                fake()->url(),
            ],
            'channels' => fake()->randomElements(
                ['instagram', 'tiktok', 'youtube', 'linkedin'],
                fake()->numberBetween(1, 4)
            ),
            'is_active' => true,
        ];
    }
}
