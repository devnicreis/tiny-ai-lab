<?php

namespace Tests\Concerns;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Services\FirebaseService;
use Mockery\MockInterface;

/**
 * Montagem do cenário de organizações dos testes (SAD 20).
 *
 * Existe porque o cenário mínimo do domínio deixou de ser "um usuário": nada
 * existe fora de uma organização (SAD 20.2), e todo teste precisa de usuário,
 * organização, participação com papel e — quase sempre — uma Brand dela. Repetir
 * esse arranjo em cada arquivo faria com que uma mudança no modelo de
 * participação exigisse a mesma edição em nove lugares.
 */
trait InteractsWithOrganizations
{
    /**
     * Organização com um membro, no papel informado.
     *
     * `plan` nulo é o estado inicial de toda organização (SAD 12.18); quem
     * precisa executar pipeline assina um plano explicitamente no próprio teste.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function organizationWith(
        User $user,
        OrganizationRole $role = OrganizationRole::Admin,
        array $attributes = [],
    ): Organization {
        $organization = Organization::factory()->create($attributes);

        $this->addMember($organization, $user, $role);

        return $organization;
    }

    protected function addMember(
        Organization $organization,
        User $user,
        OrganizationRole $role = OrganizationRole::Member,
    ): void {
        $organization->users()->attach($user->id, [
            'role' => $role->value,
            'joined_at' => now(),
        ]);
    }

    /**
     * Usuário novo já membro da organização.
     */
    protected function memberOf(
        Organization $organization,
        OrganizationRole $role = OrganizationRole::Member,
        array $attributes = [],
    ): User {
        $user = User::factory()->create($attributes);

        $this->addMember($organization, $user, $role);

        return $user;
    }

    /**
     * Brand pertencente à organização (SAD 20.2).
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function brandOf(Organization $organization, array $attributes = []): Project
    {
        return Project::factory()->for($organization)->create($attributes);
    }

    /**
     * Autentica as requisições como o usuário informado, mockando o Firebase
     * para devolver claims correspondentes ao seu `firebase_uid`.
     *
     * @return array<string, string>
     */
    protected function firebaseHeaders(User $user): array
    {
        $this->mock(FirebaseService::class, function (MockInterface $mock) use ($user) {
            $mock->shouldReceive('verifyIdToken')->andReturn([
                'sub' => $user->firebase_uid,
                'email' => $user->email,
                'name' => $user->name,
                'email_verified' => true,
            ]);
        });

        return ['Authorization' => 'Bearer valid-id-token'];
    }
}
