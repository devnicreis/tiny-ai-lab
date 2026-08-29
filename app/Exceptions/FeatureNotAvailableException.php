<?php

namespace App\Exceptions;

use App\Models\User;
use RuntimeException;

/**
 * Pipeline fora do plano do usuário (SAD 12.5 e L.6).
 *
 * O feature gating é um eixo independente do saldo: precede a verificação de
 * créditos e a dispensa. Um pipeline que o plano não libera nunca chega a
 * consultar saldo, nunca cria uma Execution e nunca fala com o n8n.
 *
 * A resposta HTTP é 403 — deliberadamente distinta do 402 de
 * `InsufficientCreditsException`. Os dois casos pedem CTAs diferentes: aqui o
 * usuário precisa de upgrade de plano, não de comprar créditos, e confundi-los
 * levaria o usuário a pagar por algo que não destravaria a ação.
 */
class FeatureNotAvailableException extends RuntimeException
{
    public function __construct(
        public readonly int $userId,
        public readonly string $plan,
        public readonly string $action,
    ) {
        parent::__construct('The requested feature is not available on the current plan.');
    }

    public static function for(User $user, string $action): self
    {
        return new self(
            userId: (int) $user->getKey(),
            plan: (string) $user->plan,
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
            'user_id' => $this->userId,
            'plan' => $this->plan,
            'action' => $this->action,
        ];
    }
}
