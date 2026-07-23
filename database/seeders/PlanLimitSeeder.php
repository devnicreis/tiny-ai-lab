<?php

namespace Database\Seeders;

use App\Models\PlanLimit;
use Illuminate\Database\Seeder;

class PlanLimitSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        PlanLimit::query()->upsert(
            [
                // Custo em créditos por ação de pipeline.
                ['plan' => 'free', 'action' => 'brand_research', 'credits' => 50],
                ['plan' => 'pro', 'action' => 'brand_research', 'credits' => 50],
                ['plan' => 'enterprise', 'action' => 'brand_research', 'credits' => 50],

                ['plan' => 'free', 'action' => 'briefing_generation', 'credits' => 5],
                ['plan' => 'pro', 'action' => 'briefing_generation', 'credits' => 5],
                ['plan' => 'enterprise', 'action' => 'briefing_generation', 'credits' => 5],

                ['plan' => 'free', 'action' => 'image_generation', 'credits' => 7],
                ['plan' => 'pro', 'action' => 'image_generation', 'credits' => 7],
                ['plan' => 'enterprise', 'action' => 'image_generation', 'credits' => 7],

                ['plan' => 'free', 'action' => 'copywriting_edit', 'credits' => 15],
                ['plan' => 'pro', 'action' => 'copywriting_edit', 'credits' => 15],
                ['plan' => 'enterprise', 'action' => 'copywriting_edit', 'credits' => 15],

                ['plan' => 'free', 'action' => 'seo_optimization', 'credits' => 10],
                ['plan' => 'pro', 'action' => 'seo_optimization', 'credits' => 10],
                ['plan' => 'enterprise', 'action' => 'seo_optimization', 'credits' => 10],

                // [A DEFINIR] — custo ainda não definido pela documentação.
                ['plan' => 'free', 'action' => 'create_article', 'credits' => 0],
                ['plan' => 'pro', 'action' => 'create_article', 'credits' => 0],
                ['plan' => 'enterprise', 'action' => 'create_article', 'credits' => 0],

                ['plan' => 'free', 'action' => 'create_post', 'credits' => 0],
                ['plan' => 'pro', 'action' => 'create_post', 'credits' => 0],
                ['plan' => 'enterprise', 'action' => 'create_post', 'credits' => 0],

                ['plan' => 'free', 'action' => 'create_ad', 'credits' => 0],
                ['plan' => 'pro', 'action' => 'create_ad', 'credits' => 0],
                ['plan' => 'enterprise', 'action' => 'create_ad', 'credits' => 0],

                // Créditos totais disponíveis por plano.
                // Enterprise possui créditos ilimitados, representado por credits = null.
                ['plan' => 'free', 'action' => 'total_credits', 'credits' => 500],
                ['plan' => 'pro', 'action' => 'total_credits', 'credits' => 10000],
                ['plan' => 'enterprise', 'action' => 'total_credits', 'credits' => null],
            ],
            ['plan', 'action'],
            ['credits']
        );
    }
}
