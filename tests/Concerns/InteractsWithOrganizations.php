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
     * Organização com o usuário no papel informado — e exatamente um Owner.
     *
     * Com `Owner` (o default), o próprio usuário é o Owner. Com `Admin` ou
     * `Member`, um Owner separado é criado: nenhuma organização de teste existe
     * sem Owner, nem com dois (SAD 20.4). O Owner criado é recuperável por
     * `ownerOf()`.
     *
     * `plan` nulo é o estado inicial de toda organização (SAD 12.18); quem
     * precisa executar pipeline assina um plano explicitamente no próprio teste.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function organizationWith(
        User $user,
        OrganizationRole $role = OrganizationRole::Owner,
        array $attributes = [],
    ): Organization {
        if ($role === OrganizationRole::Owner) {
            return Organization::factory()->withOwner($user)->create($attributes);
        }

        $organization = Organization::factory()->withOwner()->create($attributes);

        $this->addMember($organization, $user, $role);

        return $organization;
    }

    /**
     * Grava a participação diretamente, sem as regras do
     * `OrganizationMembershipService` — é montagem de cenário.
     *
     * Um segundo `Owner` é recusado pelo índice único parcial do banco.
     */
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
     *
     * @param  array<string, mixed>  $attributes
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
     * Usuário novo como Admin da organização.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function adminOf(Organization $organization, array $attributes = []): User
    {
        return $this->memberOf($organization, OrganizationRole::Admin, $attributes);
    }

    /**
     * O Owner da organização, lido do papel persistido.
     */
    protected function ownerOf(Organization $organization): User
    {
        return $organization->owner();
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
