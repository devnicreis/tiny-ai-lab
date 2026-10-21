<?php

namespace App\Exceptions;

use App\Models\Organization;
use RuntimeException;

/**
 * Violação de uma regra de participação ou de papel (SAD 20.4, ADR-010).
 *
 * Base comum das exceptions do `OrganizationMembershipService`. Cada subclasse
 * é uma regra distinta, com código de erro e status HTTP próprios, renderizada
 * por um único handler JSON em `bootstrap/app.php` — a mensagem genérica de um
 * 403 não diria ao cliente se falta permissão, se o alvo não é membro ou se o
 * Owner é intocável.
 *
 * Estende `RuntimeException` para acompanhar as demais exceptions de domínio
 * deste diretório.
 */
abstract class OrganizationMembershipException extends RuntimeException
{
    public function __construct(
        public readonly ?int $organizationId,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * Código estável do erro, para o cliente decidir o que mostrar.
     */
    abstract public function errorCode(): string;

    abstract public function status(): int;

    /**
     * Contexto anexado automaticamente pelo Laravel ao reportar a exception.
     *
     * `organization_id` é nulo apenas quando a organização não pôde ser
     * identificada — um token de convite inexistente, por exemplo.
     *
     * @return array<string, int|string|null>
     */
    public function context(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'error' => $this->errorCode(),
        ];
    }

    protected static function idOf(Organization $organization): int
    {
        return (int) $organization->getKey();
    }
}
