<?php

namespace App\Services;

use App\Exceptions\FeatureNotAvailableException;
use App\Exceptions\InsufficientCreditsException;
use App\Models\PlanLimit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Autoridade única sobre o saldo de créditos (SAD 12).
 *
 * O usuário tem dois saldos que jamais se fundem em uma coluna (SAD 12.2):
 * `plan_credits_balance`, reposto e perdido a cada ciclo, e
 * `purchased_credits_balance`, comprado avulso e que nunca expira. A soma
 * existe apenas como leitura derivada; a persistência mantém os dois separados.
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
     * Verificação otimista, usada antes de criar a Execution (SAD 12.10).
     *
     * Dois eixos independentes, nesta ordem: o plano libera o pipeline, e só
     * então o saldo cobre o custo. O gating precede a verificação de saldo
     * porque um pipeline fora do plano não deve sequer consultar créditos
     * (SAD 12.5) — e porque os desfechos são distintos: 403 pede upgrade, 402
     * pede compra de créditos.
     *
     * Serve para decidir o fluxo e não substitui a verificação definitiva feita
     * dentro da transação de `deduct()`.
     *
     * @throws FeatureNotAvailableException se o plano do usuário não libera a ação
     */
    public function canExecute(User $user, string $action): bool
    {
        if (! PlanLimit::isEnabled((string) $user->plan, $action)) {
            throw FeatureNotAvailableException::for($user, $action);
        }

        $cost = $this->cost($action);

        // Custo nulo em `plan_limits` significa ação sem teto de créditos
        // (plano ilimitado): não há saldo a comparar.
        if ($cost === null) {
            return true;
        }

        $balance = $this->getRemainingBalance($user);

        // A verificação considera a soma disponível; o débito, adiante, respeita
        // a ordem de consumo (SAD 12.3).
        return $balance['total'] === null || $balance['total'] >= $cost;
    }

    /**
     * Debita o custo da ação de forma atômica, na ordem da Seção 12.3.
     *
     * O crédito que expira é gasto antes do que não expira: consome
     * `plan_credits_balance` até zerar e recorre a `purchased_credits_balance`
     * apenas para o que faltar. Um único débito pode atravessar os dois saldos.
     *
     * Os dois saldos são escritos na mesma transação e sob o mesmo lock
     * pessimista: ler um fora do lock e debitar o outro dentro dele reabriria
     * exatamente a corrida que o lock existe para fechar (SAD 12.16).
     *
     * @throws InsufficientCreditsException se a soma dos saldos, lida no momento
     *                                      do débito, não cobrir o custo da ação
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

            $plan = (int) $locked->plan_credits_balance;
            $purchased = (int) $locked->purchased_credits_balance;

            if ($plan + $purchased < $cost) {
                throw InsufficientCreditsException::for($locked, $action, $cost);
            }

            $doPlano = min($plan, $cost);
            $doComprado = $cost - $doPlano;

            $locked->plan_credits_balance = $plan - $doPlano;
            $locked->purchased_credits_balance = $purchased - $doComprado;

            // Saldo e consumo mensal descrevem o mesmo débito por ângulos
            // opostos; escritos na mesma transação, jamais divergem.
            $locked->credits_used_this_month = (int) $locked->credits_used_this_month + $cost;

            $locked->save();

            return [
                'plan_credits_balance' => (int) $locked->plan_credits_balance,
                'purchased_credits_balance' => (int) $locked->purchased_credits_balance,
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

    /**
     * Os dois saldos e a soma derivada (SAD 12.2).
     *
     * A soma é devolvida como conveniência de leitura e nunca é persistida: a
     * distinção entre crédito que expira e crédito que não expira precisa
     * sobreviver a toda a stack (CLAUDE.md regra 27).
     *
     * `total` é `null` quando não há teto a comparar — o caso ilimitado. Hoje
     * esse caso é decidido antes, pelo custo nulo em `plan_limits`, de modo que
     * o valor devolvido aqui é sempre a soma; o `null` permanece no contrato
     * porque quem consome o array precisa tratar "sem teto" e não pode inferi-lo
     * de um zero.
     *
     * @return array{plan: int, purchased: int, total: int|null}
     */
    public function getRemainingBalance(User $user): array
    {
        $plan = (int) $user->plan_credits_balance;
        $purchased = (int) $user->purchased_credits_balance;

        return [
            'plan' => $plan,
            'purchased' => $purchased,
            'total' => $plan + $purchased,
        ];
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
