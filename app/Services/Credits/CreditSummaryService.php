<?php

namespace App\Services\Credits;

use App\Models\Organization;
use App\Models\PlanLimit;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Monta o resumo de créditos exibido no shell (Sidebar e menu do perfil).
 *
 * Só leitura: nenhum saldo é alterado aqui e nenhuma regra de débito é
 * replicada. O teto mensal vem de `plan_limits` cacheado (CLAUDE.md regra 9);
 * os saldos vêm da linha da organização e do usuário (SAD 12.14).
 */
class CreditSummaryService
{
    public function for(User $user, Organization $organization): CreditSummary
    {
        $plan = $organization->plan;

        return new CreditSummary(
            hasActivePlan: $plan !== null,
            planCreditsUsed: (int) $organization->plan_credits_used_this_month,
            monthlyLimit: $plan !== null ? PlanLimit::monthlyCreditLimit($plan) : null,
            planCreditsBalance: (int) $organization->plan_credits_balance,
            organizationPurchasedBalance: (int) $organization->purchased_credits_balance,
            // Somente a carteira do próprio usuário: nenhum papel vê a de outro
            // membro (SAD 12.2).
            personalBalance: (int) $user->purchased_credits_balance,
            canManageBilling: Gate::forUser($user)->allows('manageBilling', $organization),
        );
    }
}
