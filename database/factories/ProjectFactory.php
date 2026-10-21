<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Project;
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
            // A Marca pertence à Organização (SAD 20.2); a organização default
            // nasce sem plano (SAD 12.18) e com seu Owner (SAD 20.4). Quem
            // precisar de plano ou de saldo define no próprio teste.
            'organization_id' => fn () => Organization::factory()->withOwner()->create()->id,
            // Provenance de criação (SAD 20.2): registra quem criou a Brand e
            // nunca autoriza nada. O default é um membro da organização dona.
            'created_by_user_id' => fn (array $attributes) => Organization::query()
                ->findOrFail($attributes['organization_id'])
                ->users()
                ->value('users.id'),
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
