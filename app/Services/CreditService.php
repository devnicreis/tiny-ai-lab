<?php

namespace App\Services;

use App\Exceptions\FeatureNotAvailableException;
use App\Exceptions\InsufficientCreditsException;
use App\Exceptions\NoActivePlanException;
use App\Exceptions\PersonalCreditsConfirmationRequiredException;
use App\Models\Organization;
use App\Models\PlanLimit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Autoridade única sobre planos e saldo de créditos (SAD 12).
 *
 * A entidade de faturamento é a **Organização**, não o usuário (SAD 12.1): o
 * plano assinado e duas das três carteiras pertencem a ela. O usuário entra na
 * conta apenas pela terceira carteira — a pessoal, portátil entre organizações
 * (SAD 12.2):
 *
 *   1. `organizations.plan_credits_balance`      — expira no fim do ciclo
 *   2. `organizations.purchased_credits_balance` — nunca expira
 *   3. `users.purchased_credits_balance`         — pessoal, exige confirmação
 *
 * As três jamais se fundem em uma coluna; a soma existe apenas como leitura
 * derivada (CLAUDE.md regra 27).
 *
 * ## Por que não existe um `OrganizationCreditService` separado
 *
 * A alternativa foi avaliada e recusada. Um débito de 50 créditos pode
 * atravessar as três carteiras, e a Seção 12.3 exige que ele ocorra em **uma
 * transação e sob o mesmo lock**. Separar as carteiras da organização em outro
 * Service partiria essa transação em dois objetos: o primeiro teria de devolver
 * "sobrou tanto" para o segundo debitar, que é exatamente a leitura fora do
 * lock que a Seção 12.16 proíbe. Além disso, nenhum chamador precisa só das
 * carteiras da organização — quem pergunta por saldo pergunta pelo custo de uma
 * ação, e o custo não sabe de qual carteira sairá antes de o lock ser tomado.
 * A fronteira útil aqui é entre *cobrar por uma ação* (este Service) e
 * *creditar uma compra* (o serviço de billing do Mês 4, que grava em uma
 * carteira escolhida pelo papel do comprador, SAD 12.9) — e essa fronteira já
 * está do lado de fora.
 *
 * ## Custo e cache
 *
 * O custo de cada ação vem exclusivamente de `plan_limits`, cacheado sob a
 * chave oficial `plan_limits:{action}` (SAD N.2). Nenhum valor de crédito pode
 * ser codificado aqui — alterações comerciais acontecem apenas no banco.
 *
 * O saldo, ao contrário do custo, nunca é cacheado durante um débito
 * (SAD N.5): duas execuções concorrentes lendo um saldo em cache produziriam
 * gasto duplicado. Com organizações essa corrida deixa de ser hipotética — são
 * **dois membros diferentes** competindo pelo mesmo saldo, não duas abas do
 * mesmo usuário (SAD 12.16). Por isso `deduct()` relê as linhas sob lock dentro
 * da transação, ignorando o estado das instâncias recebidas.
 *
 * O Service é stateless e registrado como singleton no AppServiceProvider.
 */
class CreditService
{
    /**
     * Verificação otimista, usada antes de criar a Execution (SAD 12.10).
     *
     * A ordem das quatro verificações é **obrigatória** e nunca deve ser
     * reordenada (SAD 12.18):
     *
     *   1. organização sem plano  → `NoActivePlanException`                      (402)
     *   2. pipeline fora do plano → `FeatureNotAvailableException`               (403)
     *   3. saldo insuficiente     → `InsufficientCreditsException`               (402)
     *   4. toca carteira pessoal  → `PersonalCreditsConfirmationRequiredException` (409)
     *
     * A verificação 1 vir antes da 2 é **regra de segurança**: o gating trata
     * linha ausente em `plan_limits` como habilitado, e uma organização sem
     * assinatura não possui linha alguma — avaliá-lo primeiro concederia acesso
     * irrestrito justamente a quem não tem contrato. A 2 vir antes da 3 evita
     * que um pipeline fora do plano sequer consulte créditos, e mantém os
     * desfechos separados: 403 pede upgrade, 402 pede compra (SAD 12.12).
     *
     * A 3 vir antes da 4 segue a Seção 12.19: se nem as três carteiras cobrem o
     * custo, a resposta é 402 — perguntar por uma confirmação que não
     * destravaria a ação seria uma ida e volta inútil.
     *
     * O retorno é sempre `true`. Os quatro desfechos negativos são distintos e
     * levam o usuário a telas diferentes; um booleano os colapsaria em uma
     * mensagem genérica, que a Seção 12.12 proíbe expressamente. O `bool`
     * permanece na assinatura para que a chamada se leia como a pergunta que é.
     *
     * Serve para decidir o fluxo e não substitui a verificação definitiva feita
     * dentro da transação de `deduct()`.
     *
     * @throws NoActivePlanException se a organização não tem assinatura ativa
     * @throws FeatureNotAvailableException se o plano da organização não libera a ação
     * @throws InsufficientCreditsException se a soma das três carteiras não cobre o custo
     * @throws PersonalCreditsConfirmationRequiredException se o débito tocaria a carteira pessoal sem confirmação
     */
    public function canExecute(
        Organization $organization,
        User $user,
        string $action,
        bool $confirmPersonalCredits = false,
    ): bool {
        if ($organization->plan === null) {
            throw NoActivePlanException::for($organization, $action);
        }

        if (! PlanLimit::isEnabled((string) $organization->plan, $action)) {
            throw FeatureNotAvailableException::for($organization, $user, $action);
        }

        $cost = $this->cost($action);

        // Custo nulo em `plan_limits` significa ação sem teto de créditos
        // (plano ilimitado): não há saldo a comparar nem carteira a confirmar.
        // Note que isso não dispensa as duas verificações acima — ausência de
        // teto é configuração comercial de custo, não ausência de contrato
        // (SAD 12.6).
        if ($cost === null) {
            return true;
        }

        $balances = $this->getBalances($organization, $user);

        // A verificação considera a soma disponível; o débito, adiante, respeita
        // a ordem de consumo (SAD 12.3).
        if ($balances['total'] !== null && $balances['total'] < $cost) {
            throw InsufficientCreditsException::for($organization, $user, $action, $cost);
        }

        $split = $this->split(
            $cost,
            $balances['org_plan'],
            $balances['org_purchased'],
            $balances['user_personal'],
        );

        if ($split['user_personal'] > 0 && ! $confirmPersonalCredits) {
            throw PersonalCreditsConfirmationRequiredException::for($split);
        }

        return true;
    }

    /**
     * Breakdown do custo por carteira, sem debitar nada (SAD 12.19).
     *
     * É a projeção que alimenta o modal de confirmação: quanto sairia de cada
     * carteira se o débito ocorresse agora. Não verifica plano nem gating — é
     * leitura, não autorização —, e por isso jamais substitui `canExecute()`.
     *
     * `requires_confirmation` responde à única pergunta que o modal existe para
     * fazer: este débito tocaria a carteira pessoal?
     *
     * O valor é uma fotografia e envelhece: o débito real é recalculado dentro
     * da transação, sob lock, e pode divergir deste breakdown em qualquer
     * direção (SAD 12.19).
     *
     * @return array{cost: int, org_plan: int, org_purchased: int, user_personal: int, requires_confirmation: bool}
     */
    public function preview(Organization $organization, User $user, string $action): array
    {
        $balances = $this->getBalances($organization, $user);

        $split = $this->split(
            $this->costFor($action),
            $balances['org_plan'],
            $balances['org_purchased'],
            $balances['user_personal'],
        );

        return $split + ['requires_confirmation' => $split['user_personal'] > 0];
    }

    /**
     * Debita o custo da ação de forma atômica, na ordem da Seção 12.3.
     *
     * O crédito que expira é gasto antes do que não expira, e o crédito da
     * organização antes do pessoal: consome `plan_credits_balance` até zerar,
     * recorre a `purchased_credits_balance` da org para o que faltar e só então
     * toca a carteira pessoal. Um único débito pode atravessar as três.
     *
     * Tudo é lido e escrito na mesma transação e sob lock pessimista. A linha da
     * organização é sempre travada; a do usuário entra no lock **apenas quando a
     * carteira pessoal seria tocada** (SAD 12.3) — e sempre nessa ordem, org
     * antes de user, para que dois débitos concorrentes nunca se travem
     * mutuamente. Ler um saldo fora do lock e debitar outro dentro dele
     * reabriria exatamente a corrida que o lock existe para fechar (SAD 12.16).
     *
     * `$confirmPersonalCredits` autoriza a **carteira**, não um valor: o quanto
     * sai de cada uma é recalculado aqui dentro e pode ser maior, menor ou nulo
     * em relação ao breakdown exibido no 409. Se o recálculo dispensar a
     * carteira pessoal, ela simplesmente não é tocada — a flag autoriza, não
     * obriga (SAD 12.19).
     *
     * @throws InsufficientCreditsException se a soma das três carteiras, lida no
     *                                      momento do débito, não cobrir o custo
     * @throws PersonalCreditsConfirmationRequiredException se o débito exigir a
     *                                                      carteira pessoal sem confirmação
     */
    public function deduct(
        Organization $organization,
        User $user,
        string $action,
        bool $confirmPersonalCredits = false,
    ): void {
        $cost = $this->cost($action);

        // Ação sem teto de créditos: não há o que debitar nem o que contabilizar.
        if ($cost === null) {
            return;
        }

        $persistido = DB::transaction(function () use ($organization, $user, $action, $cost, $confirmPersonalCredits): array {
            // Fonte da verdade relida sob lock; o saldo em memória das
            // instâncias recebidas pode estar defasado por outro membro da
            // mesma organização.
            $lockedOrganization = Organization::query()
                ->whereKey($organization->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $orgPlan = (int) $lockedOrganization->plan_credits_balance;
            $orgPurchased = (int) $lockedOrganization->purchased_credits_balance;

            $lockedUser = null;
            $personal = 0;

            // A carteira pessoal só é lida — e só é travada — quando as duas
            // carteiras da organização não bastam.
            if ($orgPlan + $orgPurchased < $cost) {
                $lockedUser = User::query()
                    ->whereKey($user->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $personal = (int) $lockedUser->purchased_credits_balance;
            }

            $split = $this->split($cost, $orgPlan, $orgPurchased, $personal);

            // Mesma ordem de `canExecute()`: saldo insuficiente é 402 antes de
            // qualquer pergunta sobre a carteira pessoal (SAD 12.19).
            if ($orgPlan + $orgPurchased + $personal < $cost) {
                throw InsufficientCreditsException::for(
                    $lockedOrganization,
                    $lockedUser ?? $user,
                    $action,
                    $cost,
                );
            }

            if ($split['user_personal'] > 0 && ! $confirmPersonalCredits) {
                // O rollback da transação desfaz o lock e não deixa nada para
                // trás: o 409 é uma pergunta, não um débito parcial.
                throw PersonalCreditsConfirmationRequiredException::for($split);
            }

            $lockedOrganization->plan_credits_balance = $orgPlan - $split['org_plan'];
            $lockedOrganization->purchased_credits_balance = $orgPurchased - $split['org_purchased'];

            // O consumo do mês mede a carteira do plano — a única que renova e
            // a única cujo esgotamento tem significado comercial. Crédito
            // comprado, da org ou pessoal, não pertence a ciclo nenhum
            // (SAD 12.4).
            $lockedOrganization->plan_credits_used_this_month =
                (int) $lockedOrganization->plan_credits_used_this_month + $split['org_plan'];

            $lockedOrganization->save();

            if ($split['user_personal'] > 0 && $lockedUser !== null) {
                $lockedUser->purchased_credits_balance = $personal - $split['user_personal'];
                $lockedUser->save();
            }

            return [
                'organization' => [
                    'plan_credits_balance' => (int) $lockedOrganization->plan_credits_balance,
                    'purchased_credits_balance' => (int) $lockedOrganization->purchased_credits_balance,
                    'plan_credits_used_this_month' => (int) $lockedOrganization->plan_credits_used_this_month,
                ],
                'user' => $lockedUser !== null
                    ? ['purchased_credits_balance' => (int) $lockedUser->purchased_credits_balance]
                    : [],
            ];
        });

        // Mantém as instâncias do chamador coerentes com o que foi persistido,
        // sem marcar como limpas outras alterações pendentes dos models.
        $this->sincronizar($organization, $persistido['organization']);
        $this->sincronizar($user, $persistido['user']);
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
     * As três carteiras e a soma derivada (SAD 12.2).
     *
     * A soma é devolvida como conveniência de leitura e nunca é persistida: a
     * distinção entre crédito que expira e crédito que não expira, e entre
     * crédito da organização e crédito pessoal, precisa sobreviver a toda a
     * stack (CLAUDE.md regra 27). Quem exibir o agregado na interface continua
     * obrigado a mostrar a carteira pessoal separada da carteira da organização.
     *
     * `total` é `null` quando não há teto a comparar — o caso ilimitado. Hoje
     * esse caso é decidido antes, pelo custo nulo em `plan_limits`, de modo que
     * o valor devolvido aqui é sempre a soma; o `null` permanece no contrato
     * porque quem consome o array precisa tratar "sem teto" e não pode inferi-lo
     * de um zero.
     *
     * @return array{org_plan: int, org_purchased: int, user_personal: int, total: int|null}
     */
    public function getBalances(Organization $organization, User $user): array
    {
        $orgPlan = (int) $organization->plan_credits_balance;
        $orgPurchased = (int) $organization->purchased_credits_balance;
        $personal = (int) $user->purchased_credits_balance;

        return [
            'org_plan' => $orgPlan,
            'org_purchased' => $orgPurchased,
            'user_personal' => $personal,
            'total' => $orgPlan + $orgPurchased + $personal,
        ];
    }

    /**
     * Distribui o custo pelas três carteiras na ordem da Seção 12.3.
     *
     * Função pura: não lê banco, não escreve nada e é o único lugar onde a
     * ordem de consumo existe — `canExecute()`, `preview()` e `deduct()` a
     * chamam com saldos de origens diferentes, mas nunca com regras diferentes.
     *
     * A soma das três parcelas é menor que `$cost` quando as carteiras não
     * cobrem a ação; decidir o que fazer com isso pertence a quem chamou.
     *
     * @return array{cost: int, org_plan: int, org_purchased: int, user_personal: int}
     */
    protected function split(int $cost, int $orgPlan, int $orgPurchased, int $personal): array
    {
        $doPlano = min($orgPlan, $cost);
        $restante = $cost - $doPlano;

        $doCompradoDaOrg = min($orgPurchased, $restante);
        $restante -= $doCompradoDaOrg;

        $doPessoal = min($personal, $restante);

        return [
            'cost' => $cost,
            'org_plan' => $doPlano,
            'org_purchased' => $doCompradoDaOrg,
            'user_personal' => $doPessoal,
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

    /**
     * Reflete no Model do chamador o que a transação persistiu.
     *
     * @param  array<string, int>  $atributos
     */
    private function sincronizar(Organization|User $model, array $atributos): void
    {
        foreach ($atributos as $coluna => $valor) {
            $model->setAttribute($coluna, $valor);
            $model->syncOriginalAttribute($coluna);
        }
    }
}
