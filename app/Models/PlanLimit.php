<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PlanLimit extends Model
{
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
