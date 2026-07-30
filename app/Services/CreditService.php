<?php

namespace App\Services;

use App\Exceptions\InsufficientCreditsException;
use App\Models\PlanLimit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Autoridade única sobre o saldo de créditos (SAD 12).
 *
 * O custo de cada ação vem exclusivamente de `plan_limits`, cacheado sob a
 * chave oficial `plan_limits:{action}` (SAD N.2). Nenhum valor de crédito pode
 * ser codificado aqui — alterações comerciais acontecem apenas no banco.
 *
 * O saldo, ao contrário do custo, nunca é cacheado durante um débito
 * (SAD N.5): duas execuções concorrentes lendo um saldo em cache produziriam
 * gasto duplicado. Por isso `deduct()` relê a linha do usuário sob lock dentro
 * da transação, ignorando o estado da instância recebida.
 *
 * O Service é stateless e registrado como singleton no AppServiceProvider.
 */
class CreditService
{
    /**
     * Verificação otimista, usada antes de criar a Execution (SAD 12.5).
     *
     * Serve para decidir o fluxo — HTTP 402 versus continuação — e não substitui
     * a verificação definitiva feita dentro da transação de `deduct()`.
     */
    public function canExecute(User $user, string $action): bool
    {
        $cost = $this->cost($action);

        // Custo nulo em `plan_limits` significa ação sem teto de créditos
        // (plano ilimitado): não há saldo a comparar.
        if ($cost === null) {
            return true;
        }

        return $this->getRemainingBalance($user) >= $cost;
    }

    /**
     * Debita o custo da ação de forma atômica.
     *
     * O lock pessimista sobre a linha do usuário serializa débitos concorrentes:
     * a segunda transação só lê o saldo depois que a primeira registrou o seu
     * consumo, de modo que duas execuções simultâneas jamais gastam o mesmo
     * crédito.
     *
     * @throws InsufficientCreditsException se o saldo real, lido no momento do
     *                                      débito, não cobrir o custo da ação
     */
    public function deduct(User $user, string $action): void
    {
        $cost = $this->cost($action);

        // Ação sem teto de créditos: não há o que debitar nem o que contabilizar.
        if ($cost === null) {
            return;
        }

        $persistido = DB::transaction(function () use ($user, $action, $cost): array {
            // Fonte da verdade relida sob lock; o saldo em memória da instância
            // recebida pode estar defasado por outra execução do mesmo usuário.
            $locked = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->credits_balance < $cost) {
                throw InsufficientCreditsException::for($locked, $action, $cost);
            }

            $locked->decrement('credits_balance', $cost);

            // Saldo e consumo mensal descrevem o mesmo débito por ângulos
            // opostos; escritos na mesma transação, jamais divergem.
            $locked->increment('credits_used_this_month', $cost);

            return [
                'credits_balance' => (int) $locked->credits_balance,
                'credits_used_this_month' => (int) $locked->credits_used_this_month,
            ];
        });

        // Mantém a instância do chamador coerente com o que foi persistido,
        // sem marcar como limpas outras alterações pendentes do model.
        foreach ($persistido as $coluna => $valor) {
            $user->setAttribute($coluna, $valor);
            $user->syncOriginalAttribute($coluna);
        }
    }

    /**
     * Custo da ação a ser registrado em `executions.credits_consumed`.
     *
     * Exposto para que o Controller possa persistir a coluna NOT NULL sem
     * conhecer nenhum valor de crédito (CLAUDE.md regra 9): a única fonte
     * continua sendo `plan_limits`. Ausência de teto é gravada como `0`, pois a
     * execução de fato não consumiu saldo algum.
     */
    public function costFor(string $action): int
    {
        return $this->cost($action) ?? 0;
    }

    public function getRemainingBalance(User $user): int
    {
        return (int) $user->credits_balance;
    }

    /**
     * Custo da ação segundo `plan_limits`, cacheado por 60 minutos (SAD N.2).
     *
     * `null` representa ausência de teto (plano ilimitado). Hoje esse valor não
     * chega até aqui: `PlanLimit::forAction()` declara retorno `int` e converte
     * o NULL do banco em `0`. O tratamento permanece porque o contrato do
     * Service é o da coluna `plan_limits.credits`, que é nullable.
     */
    protected function cost(string $action): ?int
    {
        return PlanLimit::forAction($action);
    }
}
