<?php

namespace App\Services\Credits;

/**
 * O que a interface mostra sobre créditos na organização ativa (SAD 10.12).
 *
 * A barra é o **consumo do plano da organização** no mês — nunca a soma das
 * carteiras (CLAUDE.md regra 27). As três carteiras viajam separadas para o
 * tooltip. A capacidade de assinar já chega resolvida pelo backend: a view não
 * deduz nada do papel (SAD 12.12).
 */
final readonly class CreditSummary
{
    public function __construct(
        public bool $hasActivePlan,
        public int $planCreditsUsed,
        public ?int $monthlyLimit,
        public int $planCreditsBalance,
        public int $organizationPurchasedBalance,
        public int $personalBalance,
        public bool $canManageBilling,
    ) {}

    /**
     * Existe denominador para a barra?
     *
     * Sem plano não há ciclo; sem teto mensal definido — ou ilimitado — não há
     * o que dividir. Nos dois casos a interface mostra só o consumo.
     */
    public function hasLimit(): bool
    {
        return $this->hasActivePlan
            && $this->monthlyLimit !== null
            && $this->monthlyLimit > 0;
    }

    /**
     * Percentual consumido do teto mensal, de 0 a 100, ou `null` sem teto.
     */
    public function usagePercent(): ?int
    {
        if (! $this->hasLimit()) {
            return null;
        }

        return (int) min(100, round($this->planCreditsUsed * 100 / $this->monthlyLimit));
    }

    public function usageLabel(): string
    {
        if (! $this->hasActivePlan) {
            return 'No active plan';
        }

        return $this->hasLimit()
            ? "{$this->planCreditsUsed}/{$this->monthlyLimit} credits used"
            : "{$this->planCreditsUsed} credits used";
    }

    /**
     * A ação oferecida ao passar o mouse sobre o indicador.
     *
     * Sem plano, nenhum crédito resolve — nem o pessoal —, então ninguém recebe
     * "Buy AI credits": o Owner é convidado a assinar e os demais a pedir ao
     * Owner (SAD 12.12, CTA por papel).
     */
    public function ctaLabel(): string
    {
        if (! $this->hasActivePlan) {
            return $this->canManageBilling ? 'Subscribe' : 'Ask your organization owner';
        }

        return 'Buy AI credits';
    }

    /**
     * O CTA leva a uma ação (assinar ou comprar), e não a um recado?
     *
     * "Ask your organization owner" é orientação, não controle: a view não o
     * desenha como botão.
     */
    public function ctaIsAction(): bool
    {
        return $this->hasActivePlan || $this->canManageBilling;
    }

    /**
     * As três carteiras em uma linha, para o tooltip nativo do menu do perfil.
     */
    public function walletsLabel(): string
    {
        return "Plan credits: {$this->planCreditsBalance} · "
            ."Purchased credits (organization): {$this->organizationPurchasedBalance} · "
            ."Personal credits: {$this->personalBalance}";
    }
}
