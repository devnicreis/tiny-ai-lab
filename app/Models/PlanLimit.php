<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PlanLimit extends Model
{
    /**
     * Action que guarda o teto mensal de créditos de um plano, e não um custo.
     *
     * Os créditos mensais de cada plano comercial são [A DEFINIR] (SAD 12.6):
     * enquanto nenhuma linha `(plan, monthly_credit_limit)` existir, a interface
     * mostra só o consumo, sem denominador. As linhas legadas `total_credits`
     * descrevem os planos `free | pro | enterprise`, que nenhuma organização
     * usa, e não são lidas aqui.
     */
    public const MONTHLY_CREDIT_LIMIT_ACTION = 'monthly_credit_limit';

    protected $fillable = [
        'plan',
        'action',
        'credits',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'credits' => 'integer',
            'is_enabled' => 'boolean',
        ];
    }

    public static function forAction(string $action): ?int
    {
        return Cache::remember(
            "plan_limits:{$action}",
            now()->addMinutes(60),
            fn () => static::where('action', $action)->value('credits')
        );
    }

    /**
     * Teto mensal de créditos do plano, ou `null` quando não há um definido.
     *
     * `null` cobre dois casos que a interface trata igual — linha ausente e
     * `credits = null` (ilimitado, SAD 12.15): não há denominador para exibir.
     * Escopado por plano desde o início, sob chave própria (CLAUDE.md regra 9).
     */
    public static function monthlyCreditLimit(string $plan): ?int
    {
        $action = self::MONTHLY_CREDIT_LIMIT_ACTION;

        return Cache::remember(
            "plan_limits:{$plan}:{$action}",
            now()->addMinutes(60),
            fn () => static::query()
                ->where('plan', $plan)
                ->where('action', $action)
                ->value('credits')
        );
    }

    /**
     * O plano libera esta ação? (SAD 12.5)
     *
     * Diferente de `forAction()`, esta consulta é escopada pelo plano: o gating
     * é justamente a dimensão em que os planos divergem, e responder pela
     * primeira linha da action daria a todos o acesso do primeiro plano gravado.
     *
     * A ausência de linha `(plan, action)` devolve `true`. A coluna nasceu com
     * `default(true)` e nenhuma configuração de gating existe ainda (SAD 12.15):
     * tratar a ausência como bloqueio transformaria uma tabela incompleta em
     * negativa de serviço. Bloqueio exige linha gravada dizendo `false`.
     *
     * Cacheado sob chave própria — nunca sob `plan_limits:{action}`, que
     * transporta custo e é a chave cuja migração para escopo por plano depende
     * de ADR (SAD 12.15).
     */
    public static function isEnabled(string $plan, string $action): bool
    {
        return Cache::remember(
            "plan_limits:{$plan}:{$action}:is_enabled",
            now()->addMinutes(60),
            fn () => (bool) (static::query()
                ->where('plan', $plan)
                ->where('action', $action)
                ->value('is_enabled') ?? true)
        );
    }
}
