<?php

namespace App\Exceptions;

use App\Models\Organization;
use RuntimeException;

/**
 * A organização ativa não tem assinatura (SAD 12.18).
 *
 * `organizations.plan = null` não é plano gratuito, trial nem estado de erro:
 * é o estado inicial normal de toda organização, inclusive da "Personal"
 * criada no primeiro login. Nenhum pipeline executa nesse estado — nem com
 * saldo comprado pela organização, nem com a carteira pessoal do usuário.
 *
 * É o **primeiro** dos três eixos de bloqueio e precede o feature gating. A
 * ordem é regra de segurança, não estilo: o gating trata linha ausente em
 * `plan_limits` como habilitado, de modo que avaliá-lo antes concederia acesso
 * irrestrito justamente a quem não tem contrato (SAD 12.18).
 *
 * A resposta HTTP é 402 com a CTA "Subscribe to a plan" — distinta do 402 de
 * `InsufficientCreditsException`, que pede compra de créditos: comprar
 * créditos não resolve a ausência de assinatura (SAD 12.12).
 *
 * Estende `RuntimeException` — que é uma `Exception` — para acompanhar as
 * demais exceptions de domínio deste diretório.
 */
class NoActivePlanException extends RuntimeException
{
    public function __construct(
        public readonly int $organizationId,
        public readonly ?string $action = null,
    ) {
        parent::__construct('This organization has no active plan.');
    }

    public static function for(Organization $organization, ?string $action = null): self
    {
        return new self(
            organizationId: (int) $organization->getKey(),
            action: $action,
        );
    }

    /**
     * Contexto anexado automaticamente pelo Laravel ao reportar a exception.
     *
     * @return array<string, int|string|null>
     */
    public function context(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'action' => $this->action,
        ];
    }
}
