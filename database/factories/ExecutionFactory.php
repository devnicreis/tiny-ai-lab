<?php

namespace Database\Factories;

use App\Models\Execution;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Execution>
 */
class ExecutionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            // O dono da Execution é sempre o dono da Brand.
            'user_id' => fn (array $attributes) => Project::find($attributes['project_id'])?->user_id,
            'pipeline_type' => fake()->randomElement([
                'brand_research',
                'briefing_generation',
                'image_generation',
                'copywriting_edit',
                'seo_optimization',
            ]),
            'status' => 'queued',
            'config' => [
                'language' => 'pt',
                'inputs' => [],
            ],
            'result' => null,
            'tokens_used' => 0,
            'credits_consumed' => fake()->numberBetween(1, 50),
        ];
    }

    /**
     * Indicate that the execution is queued.
     */
    public function queued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'queued',
            'result' => null,
            'completed_at' => null,
            'failed_at' => null,
        ]);
    }

    /**
     * Indicate that the execution is processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'result' => null,
            'completed_at' => null,
            'failed_at' => null,
        ]);
    }

    /**
     * Indicate that the execution has completed successfully.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'result' => [
                'diagnosis' => [],
                'calendar' => [],
                'copies' => [],
            ],
            'completed_at' => now(),
            'failed_at' => null,
        ]);
    }

    /**
     * Indicate that the execution has failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'result' => null,
            'error_message' => fake()->sentence(),
            'completed_at' => null,
            'failed_at' => now(),
        ]);
    }
}
