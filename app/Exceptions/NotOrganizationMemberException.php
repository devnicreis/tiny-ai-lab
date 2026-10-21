<?php

namespace App\Exceptions;

use App\Models\Organization;
use Symfony\Component\HttpFoundation\Response;

/**
 * O usuário alvo — ou o solicitante — não participa da organização.
 *
 * Na transferência de posse, destino fora da organização não é aceito: é
 * preciso convidar primeiro e transferir depois do aceite (SAD 20.4,
 * invariante 3).
 */
class NotOrganizationMemberException extends OrganizationMembershipException
{
    public function __construct(
        int $organizationId,
        string $message,
        public readonly int $userId,
    ) {
        parent::__construct($organizationId, $message);
    }

    public static function for(Organization $organization, int $userId): self
    {
        return new self(
            self::idOf($organization),
            'The user is not a member of this organization.',
            $userId,
        );
    }

    public function errorCode(): string
    {
        return 'not_organization_member';
    }

    public function status(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }

    /**
     * @return array<string, int|string>
     */
    public function context(): array
    {
        return [...parent::context(), 'user_id' => $this->userId];
    }
}
