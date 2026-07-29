<?php

namespace App\Exceptions;

use App\Models\User;
use RuntimeException;

/**
 * Saldo insuficiente para a ação solicitada (SAD 12.7 e L.6).
 *
 * Encerra a operação antes de qualquer débito: a Execution não é criada e
 * nenhuma chamada ao n8n ocorre. A resposta HTTP correspondente é 402,
 * registrada em `bootstrap/app.php` junto com a CTA "Buy AI Credits".
 *
 * O custo transportado aqui vem sempre de `plan_limits`; nenhum valor de
 * crédito é conhecido por esta classe.
 */
class InsufficientCreditsException extends RuntimeException
{
    public function __construct(
        public readonly int $userId,
        public readonly string $action,
        public readonly int $required,
        public readonly int $available,
    ) {
        parent::__construct('Insufficient credits for the requested action.');
    }

    public static function for(User $user, string $action, int $required): self
    {
        return new self(
            userId: (int) $user->getKey(),
            action: $action,
            required: $required,
            available: (int) $user->credits_balance,
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
            'action' => $this->action,
            'required' => $this->required,
            'available' => $this->available,
        ];
    }
}
