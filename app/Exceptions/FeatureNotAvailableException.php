<?php

namespace App\Exceptions;

use App\Models\Organization;
use App\Models\User;
use RuntimeException;

/**
 * Pipeline fora do plano da organização ativa (SAD 12.5 e L.6).
 *
 * O feature gating é um eixo independente do saldo: precede a verificação de
 * créditos e a dispensa. Um pipeline que o plano não libera nunca chega a
 * consultar saldo, nunca cria uma Execution e nunca fala com o n8n.
 *
 * O plano que reprova é o **da organização**, nunca o do usuário: o mesmo
 * usuário pode ter acesso a `image_generation` em uma organização e não ter em
 * outra (SAD 12.5). O `userId` viaja junto apenas como contexto de log — quem
 * pediu —, jamais como critério da decisão.
 *
 * A resposta HTTP é 403 — deliberadamente distinta do 402 de
 * `InsufficientCreditsException`. Os dois casos pedem CTAs diferentes: aqui o
 * usuário precisa de upgrade de plano, não de comprar créditos, e confundi-los
 * levaria o usuário a pagar por algo que não destravaria a ação.
 */
class FeatureNotAvailableException extends RuntimeException
{
    public function __construct(
        public readonly int $organizationId,
        public readonly int $userId,
        public readonly string $plan,
        public readonly string $action,
    ) {
        parent::__construct('This feature is not included in your plan.');
    }

    public static function for(Organization $organization, User $user, string $action): self
    {
        return new self(
            organizationId: (int) $organization->getKey(),
            userId: (int) $user->getKey(),
            plan: (string) $organization->plan,
            action: $action,
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
            'plan' => $this->plan,
            'action' => $this->action,
        ];
    }
}
