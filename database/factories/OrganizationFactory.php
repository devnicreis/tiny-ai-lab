<?php

namespace Database\Factories;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => Str::slug(fake()->unique()->company().'-'.Str::random(6)),
            // Nasce sem plano — nenhuma organização recebe plano default
            // (SAD 12.18 e 20.3).
            'plan' => null,
            'plan_credits_balance' => 0,
            'plan_credits_used_this_month' => 0,
            'purchased_credits_balance' => 0,
            'last_credit_reset_at' => null,
        ];
    }

    /**
     * Organização com seu Owner — o informado ou um usuário novo (SAD 20.4).
     *
     * Toda organização válida tem exatamente um Owner. A participação é
     * escrita diretamente porque este é o nascimento da organização: o
     * `OrganizationMembershipService` recusa `owner` fora da transferência de
     * posse, e não há Owner anterior a transferir.
     */
    public function withOwner(?User $owner = null): static
    {
        return $this->afterCreating(function (Organization $organization) use ($owner): void {
            $organization->users()->attach(($owner ?? User::factory()->create())->id, [
                'role' => OrganizationRole::Owner->value,
                'joined_at' => now(),
            ]);
        });
    }
}
