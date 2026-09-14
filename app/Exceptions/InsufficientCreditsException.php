<?php

namespace App\Exceptions;

use App\Models\Organization;
use App\Models\User;
use RuntimeException;

/**
 * Saldo insuficiente para a ação solicitada (SAD 12.12 e L.6).
 *
 * Encerra a operação antes de qualquer débito: a Execution não é criada,
 * nenhuma carteira é parcialmente debitada e nenhuma chamada ao n8n ocorre. A
 * resposta HTTP correspondente é 402, registrada em `bootstrap/app.php` junto
 * com a CTA "Buy AI Credits".
 *
 * O custo transportado aqui vem sempre de `plan_limits`; nenhum valor de
 * crédito é conhecido por esta classe.
 */
class InsufficientCreditsException extends RuntimeException
{
    public function __construct(
        public readonly int $organizationId,
        public readonly int $userId,
        public readonly string $action,
        public readonly int $required,
        public readonly int $available,
    ) {
        parent::__construct('Not enough credits to run this action.');
    }

    /**
     * `available` é a soma das três carteiras — a mesma grandeza que a
     * verificação da Seção 12.10 compara com o custo. A soma existe apenas
     * aqui, como diagnóstico; nenhum saldo somado é persistido (SAD 12.2).
     *
     * A carteira pessoal entra na soma mesmo quando a requisição não trouxe a
     * confirmação: quando nem as três carteiras cobrem o custo, o desfecho é
     * 402 — pedir confirmação de uma carteira que tampouco resolveria seria
     * uma pergunta inútil (SAD 12.19).
     */
    public static function for(Organization $organization, User $user, string $action, int $required): self
    {
        return new self(
            organizationId: (int) $organization->getKey(),
            userId: (int) $user->getKey(),
            action: $action,
            required: $required,
            available: (int) $organization->plan_credits_balance
                + (int) $organization->purchased_credits_balance
                + (int) $user->purchased_credits_balance,
        );
    }

    /**
     * Contexto anexado automaticamente pelo Laravel ao reportar a exception.
     *
     * @return array<string, int|string>
     */
    public function context(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'user_id' => $this->userId,
            'action' => $this->action,
            'required' => $this->required,
            'available' => $this->available,
        ];
    }
}
