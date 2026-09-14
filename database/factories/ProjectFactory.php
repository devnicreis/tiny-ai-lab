<?php

namespace Database\Factories;

use App\Enums\OrganizationRole;
use App\Models\Organization;
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
            'organization_id' => fn () => self::createOrganizationWithAdmin(),
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

    /**
     * Cria a Organização dona da Brand e um Admin para ela.
     *
     * Substitui o antigo `'user_id' => User::factory()`: a Marca pertence à
     * Organização (SAD 20.2), e o usuário entra pela pivot, não pela Marca. A
     * organização nasce sem plano (SAD 12.18) — quem precisar de plano ou de
     * saldo define no próprio teste.
     */
    private static function createOrganizationWithAdmin(): int
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        $organization->users()->attach($user->id, [
            'role' => OrganizationRole::Admin->value,
            'joined_at' => now(),
        ]);

        return $organization->id;
    }
}
