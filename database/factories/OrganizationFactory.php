<?php

namespace Database\Factories;

use App\Models\Organization;
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
}
