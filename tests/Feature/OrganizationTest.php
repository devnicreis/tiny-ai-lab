<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Exceptions\InsufficientOrganizationRoleException;
use App\Exceptions\InvalidOwnershipTransferException;
use App\Exceptions\NotOrganizationMemberException;
use App\Exceptions\OwnerIsImmutableException;
use App\Exceptions\OwnerRoleRequiresTransferException;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationMembershipService;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithOrganizations;
use Tests\TestCase;

/**
 * Três papéis, invariantes do Owner e perda de acesso (SAD 20.4 e 20.6,
 * ADR-010).
 *
 * Faturamento, compra para a org, papéis, transferência de posse e exclusão
 * são do Owner; Marcas, membros (só Members, no caso do Admin) e relatórios de
 * consumo são de Owner e Admin; o Member usa as ferramentas de IA.
 *
 * > 🔁 SUPERSEDED (ADR-010): os testes "Member gerencia Marcas" e "último
 * > Admin não pode ser removido" foram substituídos pelos testes do Owner.
 */
class OrganizationTest extends TestCase
{
    use InteractsWithOrganizations, RefreshDatabase;

    private function membership(): OrganizationMembershipService
    {
        return app(OrganizationMembershipService::class);
    }

    private function roleOf(Organization $organization, User $user): ?OrganizationRole
    {
        return $user->roleIn($organization);
    }

    // ---------------------------------------------------------------------
    // Invariante do Owner (SAD 20.4)
    // ---------------------------------------------------------------------

    /**
     * O teto do invariante é do banco: o índice único parcial recusa um
     * segundo Owner mesmo para uma escrita que não passe pelo domínio.
     */
    public function test_database_rejects_second_owner(): void
    {
        $owner = User::factory()->create();
        $organization = $this->organizationWith($owner);
        $admin = $this->adminOf($organization);

        // O índice é por organização: outra organização tem o próprio Owner.
        $this->organizationWith(User::factory()->create());
        $this->assertSame(1, $organization->ownerCount());

        // Nem um Owner novo entrando...
        $this->assertThrows(
            fn () => $this->memberOf($organization, OrganizationRole::Owner),
            UniqueConstraintViolationException::class,
        );

        // ...nem um Admin promovido por escrita direta na pivot.
        $this->assertThrows(
            fn () => $organization->users()->updateExistingPivot($admin->id, [
                'role' => OrganizationRole::Owner->value,
            ]),
            UniqueConstraintViolationException::class,
        );

        $this->assertSame(1, $organization->ownerCount());
        $this->assertTrue($organization->isOwner($owner));
    }

    /**
     * Ninguém remove nem rebaixa o Owner — nem um Admin, nem ele próprio. Sair
     * é uma remoção como outra qualquer (SAD 20.4, invariantes 2 e 5).
     */
    public function test_owner_cannot_be_removed_or_demoted(): void
    {
        $owner = User::factory()->create();
        $organization = $this->organizationWith($owner);
        $admin = $this->adminOf($organization);
        $member = $this->memberOf($organization);

        $this->assertThrows(
            fn () => $this->membership()->removeMember($organization, $owner, $owner),
            OwnerIsImmutableException::class,
        );
        $this->assertThrows(
            fn () => $this->membership()->removeMember($organization, $admin, $owner),
            OwnerIsImmutableException::class,
        );
        $this->assertThrows(
            fn () => $this->membership()->changeRole($organization, $owner, $owner, OrganizationRole::Admin),
            OwnerIsImmutableException::class,
        );
        $this->assertThrows(
            fn () => $this->membership()->changeRole($organization, $owner, $owner, OrganizationRole::Member),
            OwnerIsImmutableException::class,
        );

        // E ninguém vira Owner por fora da transferência.
        $this->assertThrows(
            fn () => $this->membership()->changeRole($organization, $owner, $member, OrganizationRole::Owner),
            OwnerRoleRequiresTransferException::class,
        );
        $this->assertThrows(
            fn () => $this->membership()->addMember($organization, User::factory()->create(), OrganizationRole::Owner),
            OwnerRoleRequiresTransferException::class,
        );

        $this->assertSame(OrganizationRole::Owner, $this->roleOf($organization, $owner));
        $this->assertSame(OrganizationRole::Member, $this->roleOf($organization, $member));
        $this->assertSame(1, $organization->ownerCount());
    }

    /**
     * O destino vira Owner e o Owner anterior vira Admin na mesma transação —
     * e uma falha no meio desfaz as duas escritas (SAD 20.4, invariante 3).
     */
    public function test_transfer_ownership_swaps_roles_atomically(): void
    {
        $owner = User::factory()->create();
        $organization = $this->organizationWith($owner);
        $member = $this->memberOf($organization);

        $this->membership()->transferOwnership($organization, $owner, $member);

        $this->assertSame(OrganizationRole::Owner, $this->roleOf($organization, $member));
        $this->assertSame(OrganizationRole::Admin, $this->roleOf($organization, $owner));
        $this->assertSame(1, $organization->ownerCount());
        $this->assertSame($member->id, $organization->owner()->id);

        // O Owner anterior virou Admin: a autoridade dele é relida do banco,
        // não do objeto que o chamador ainda segura.
        $this->assertThrows(
            fn () => $this->membership()->transferOwnership($organization, $owner, $member),
            InsufficientOrganizationRoleException::class,
        );

        // Atomicidade: a promoção falha **depois** de o rebaixamento ter sido
        // escrito. Sem a transação, a organização ficaria sem Owner.
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('The failure-injection trigger uses SQLite syntax.');
        }

        DB::statement(<<<'SQL'
            CREATE TRIGGER block_owner_promotion BEFORE UPDATE OF role ON organization_user
            WHEN NEW.role = 'owner'
            BEGIN SELECT RAISE(ABORT, 'owner promotion blocked'); END;
        SQL);

        $this->assertThrows(
            fn () => $this->membership()->transferOwnership($organization, $member, $owner),
            QueryException::class,
        );

        $this->assertSame(OrganizationRole::Owner, $this->roleOf($organization, $member));
        $this->assertSame(OrganizationRole::Admin, $this->roleOf($organization, $owner));
        $this->assertSame(1, $organization->ownerCount());
    }

    /**
     * Destino fora da organização não é aceito: é preciso convidar primeiro
     * (SAD 20.4, invariante 3). O Owner continua sendo quem era.
     */
    public function test_transfer_to_non_member_is_rejected(): void
    {
        $owner = User::factory()->create();
        $organization = $this->organizationWith($owner);

        // Membro de **outra** organização continua sendo estranho a esta.
        $outsider = User::factory()->create();
        $this->organizationWith($outsider);

        $this->assertThrows(
            fn () => $this->membership()->transferOwnership($organization, $owner, $outsider),
            NotOrganizationMemberException::class,
        );
        $this->assertThrows(
            fn () => $this->membership()->transferOwnership($organization, $owner, $owner),
            InvalidOwnershipTransferException::class,
        );

        $this->assertFalse($outsider->belongsToOrganization($organization));
        $this->assertSame($owner->id, $organization->owner()->id);
        $this->assertSame(1, $organization->ownerCount());
    }

    // ---------------------------------------------------------------------
    // Membros e papéis (SAD 20.4)
    // ---------------------------------------------------------------------

    /**
     * Atribuir papéis é exclusivo do Owner, limitado a `admin` ↔ `member`.
     */
    public function test_admin_cannot_assign_roles(): void
    {
        $owner = User::factory()->create();
        $organization = $this->organizationWith($owner);
        $admin = $this->adminOf($organization);
        $member = $this->memberOf($organization);

        $this->assertFalse($admin->can('assignRoles', $organization));
        $this->assertFalse($member->can('assignRoles', $organization));
        $this->assertTrue($owner->can('assignRoles', $organization));

        // Nem promover um Member, nem rebaixar a si mesmo.
        $this->assertThrows(
            fn () => $this->membership()->changeRole($organization, $admin, $member, OrganizationRole::Admin),
            InsufficientOrganizationRoleException::class,
        );
        $this->assertThrows(
            fn () => $this->membership()->changeRole($organization, $admin, $admin, OrganizationRole::Member),
            InsufficientOrganizationRoleException::class,
        );

        $this->assertSame(OrganizationRole::Member, $this->roleOf($organization, $member));
        $this->assertSame(OrganizationRole::Admin, $this->roleOf($organization, $admin));

        // O Owner, sim, nos dois sentidos.
        $this->membership()->changeRole($organization, $owner, $member, OrganizationRole::Admin);
        $this->assertSame(OrganizationRole::Admin, $this->roleOf($organization, $member));

        $this->membership()->changeRole($organization, $owner, $member, OrganizationRole::Member);
        $this->assertSame(OrganizationRole::Member, $this->roleOf($organization, $member));
    }

    /**
     * O Admin remove somente Members; o Owner remove Admins e Members; o
     * Member não remove ninguém (SAD 20.4).
     */
    public function test_admin_removes_member_but_not_admin_or_owner(): void
    {
        $owner = User::factory()->create();
        $organization = $this->organizationWith($owner);
        $admin = $this->adminOf($organization);
        $otherAdmin = $this->adminOf($organization);
        $member = $this->memberOf($organization);
        $otherMember = $this->memberOf($organization);

        $this->assertTrue($admin->can('manageMembers', $organization));
        $this->assertFalse($member->can('manageMembers', $organization));

        $this->membership()->removeMember($organization, $admin, $member);
        $this->assertFalse($member->belongsToOrganization($organization));

        $this->assertThrows(
            fn () => $this->membership()->removeMember($organization, $admin, $otherAdmin),
            InsufficientOrganizationRoleException::class,
        );
        $this->assertThrows(
            fn () => $this->membership()->removeMember($organization, $admin, $owner),
            OwnerIsImmutableException::class,
        );
        $this->assertThrows(
            fn () => $this->membership()->removeMember($organization, $otherMember, $admin),
            InsufficientOrganizationRoleException::class,
        );
        // Quem já saiu não é mais alvo.
        $this->assertThrows(
            fn () => $this->membership()->removeMember($organization, $admin, $member),
            NotOrganizationMemberException::class,
        );

        $this->assertTrue($otherAdmin->belongsToOrganization($organization));
        $this->assertTrue($owner->belongsToOrganization($organization));

        // O Owner alcança o Admin.
        $this->membership()->removeMember($organization, $owner, $otherAdmin);
        $this->assertFalse($otherAdmin->belongsToOrganization($organization));
        $this->assertSame(1, $organization->ownerCount());
    }

    // ---------------------------------------------------------------------
    // Matriz da OrganizationPolicy (SAD 20.4)
    // ---------------------------------------------------------------------

    /**
     * Faturamento e compra para a org são exclusivos do Owner. Os demais veem
     * a organização e os saldos (SAD 12.12, CTA por papel).
     */
    public function test_only_owner_manages_billing_and_purchases_for_org(): void
    {
        $owner = User::factory()->create();
        $organization = $this->organizationWith($owner);
        $admin = $this->adminOf($organization);
        $member = $this->memberOf($organization);

        $this->assertTrue($owner->can('manageBilling', $organization));
        $this->assertTrue($owner->can('purchaseForOrganization', $organization));
        $this->assertTrue($owner->can('transferOwnership', $organization));

        foreach ([$admin, $member] as $user) {
            $this->assertFalse($user->can('manageBilling', $organization));
            $this->assertFalse($user->can('purchaseForOrganization', $organization));
            $this->assertFalse($user->can('transferOwnership', $organization));
            $this->assertTrue($user->can('view', $organization));
        }
    }

    /**
     * Excluir — e, até decisão, editar — a organização é só do Owner. A
     * exclusão em si é **[A DEFINIR]** (SAD 20.4): existe apenas a ability.
     */
    public function test_only_owner_can_delete_organization(): void
    {
        $owner = User::factory()->create();
        $organization = $this->organizationWith($owner);
        $admin = $this->adminOf($organization);
        $member = $this->memberOf($organization);
        $outsider = User::factory()->create();

        $this->assertTrue($owner->can('delete', $organization));
        $this->assertTrue($owner->can('update', $organization));

        foreach ([$admin, $member, $outsider] as $user) {
            $this->assertFalse($user->can('delete', $organization));
            $this->assertFalse($user->can('update', $organization));
        }
    }

    /**
     * O cliente recebe a regra que barrou a operação, não um 403 genérico.
     */
    public function test_membership_violations_render_semantic_json(): void
    {
        $organization = $this->organizationWith(User::factory()->create());
        $handler = app(ExceptionHandler::class);
        $request = Request::create('/api/organizations/members', 'DELETE');

        $cases = [
            [InsufficientOrganizationRoleException::for($organization), 403, 'insufficient_organization_role'],
            [OwnerIsImmutableException::for($organization), 409, 'owner_is_immutable'],
            [OwnerRoleRequiresTransferException::for($organization), 422, 'owner_role_requires_transfer'],
            [NotOrganizationMemberException::for($organization, 42), 422, 'not_organization_member'],
        ];

        foreach ($cases as [$exception, $status, $error]) {
            $response = $handler->render($request, $exception);

            $this->assertSame($status, $response->getStatusCode());
            $this->assertSame($error, $response->getData(true)['error']);
            $this->assertSame($exception->getMessage(), $response->getData(true)['message']);
        }
    }

    // ---------------------------------------------------------------------
    // Sair da organização (SAD 20.4, invariante 5)
    // ---------------------------------------------------------------------

    public function test_member_can_leave_organization(): void
    {
        $owner = User::factory()->create();
        $organization = $this->organizationWith($owner);
        $member = $this->memberOf($organization, OrganizationRole::Member);

        $this->assertTrue($member->can('leave', $organization));

        $this->membership()->leave($organization, $member);

        $this->assertNull($this->roleOf($organization, $member));
        $this->assertSame(OrganizationRole::Owner, $this->roleOf($organization, $owner));
    }

    public function test_admin_can_leave_organization(): void
    {
        $owner = User::factory()->create();
        $organization = $this->organizationWith($owner);
        $admin = $this->memberOf($organization, OrganizationRole::Admin);

        $this->assertTrue($admin->can('leave', $organization));

        $this->membership()->leave($organization, $admin);

        $this->assertNull($this->roleOf($organization, $admin));
        $this->assertSame(1, $organization->fresh()->ownerCount());
    }

    public function test_owner_cannot_leave_without_transferring(): void
    {
        $owner = User::factory()->create();
        $organization = $this->organizationWith($owner);
        $admin = $this->memberOf($organization, OrganizationRole::Admin);

        $this->assertFalse($owner->can('leave', $organization));

        try {
            $this->membership()->leave($organization, $owner);
            $this->fail('The owner left the organization.');
        } catch (OwnerIsImmutableException $exception) {
            $this->assertStringContainsString('Transfer ownership', $exception->getMessage());
        }

        $this->assertSame(OrganizationRole::Owner, $this->roleOf($organization, $owner));

        // Depois de transferir, o ex-Owner é Admin e pode sair.
        $this->membership()->transferOwnership($organization, $owner, $admin);
        $this->membership()->leave($organization, $owner);

        $this->assertNull($this->roleOf($organization, $owner));
        $this->assertSame(OrganizationRole::Owner, $this->roleOf($organization, $admin));
    }

    public function test_leaving_decides_by_the_role_stored_in_the_database(): void
    {
        $owner = User::factory()->create();
        $organization = $this->organizationWith($owner);
        $admin = $this->memberOf($organization, OrganizationRole::Admin);

        // O objeto $admin foi carregado antes da transferência; o papel vale
        // o do banco, relido dentro do lock.
        $stale = User::query()->findOrFail($admin->id);
        $this->membership()->transferOwnership($organization, $owner, $admin);

        $this->expectException(OwnerIsImmutableException::class);

        $this->membership()->leave($organization, $stale);
    }

    public function test_non_member_cannot_leave(): void
    {
        $organization = $this->organizationWith(User::factory()->create());
        $stranger = User::factory()->create();

        $this->assertFalse($stranger->can('leave', $organization));

        $this->expectException(NotOrganizationMemberException::class);

        $this->membership()->leave($organization, $stranger);
    }

    public function test_leaving_clears_active_organization_for_that_user(): void
    {
        $owner = User::factory()->create(['firebase_uid' => 'uid-owner']);
        $organization = $this->organizationWith($owner);
        $member = $this->memberOf($organization, OrganizationRole::Member, [
            'firebase_uid' => 'uid-member',
        ]);

        $brand = $this->brandOf($organization);

        $this->getJson("/api/projects/{$brand->id}", $this->firebaseHeaders($member))->assertOk();

        $this->membership()->leave($organization, $member);

        // A sessão ainda aponta para a organização; a participação é
        // reconferida e o contexto desaparece (SAD 20.5).
        $this->getJson("/api/projects/{$brand->id}", $this->firebaseHeaders($member))
            ->assertStatus(409)
            ->assertJsonPath('error', 'organization_required');

        $this->assertFalse($member->fresh()->can('view', $brand));
    }

    // ---------------------------------------------------------------------
    // Contexto e perda de acesso (SAD 20.5 e 20.7)
    // ---------------------------------------------------------------------

    /**
     * Sair da organização encerra o acesso na requisição seguinte (SAD 20.5).
     *
     * A sessão ainda aponta para a organização, e é justamente por isso que a
     * participação é reconferida a cada acesso em vez de confiar no contexto
     * gravado.
     */
    public function test_removed_member_loses_access_immediately(): void
    {
        $owner = User::factory()->create(['firebase_uid' => 'uid-owner']);
        $organization = $this->organizationWith($owner);
        $member = $this->memberOf($organization, OrganizationRole::Member, [
            'firebase_uid' => 'uid-member',
        ]);

        $brand = $this->brandOf($organization);

        $headers = $this->firebaseHeaders($member);

        $this->getJson("/api/projects/{$brand->id}", $headers)->assertOk();

        $this->membership()->removeMember($organization, $owner, $member);

        // Sem participação não há Brand a ver nem contexto a assumir: a Policy
        // reprova e o middleware deixa de encontrar organização ativa.
        $this->getJson("/api/projects/{$brand->id}", $this->firebaseHeaders($member))
            ->assertStatus(409)
            ->assertJsonPath('error', 'organization_required');

        $this->assertFalse($member->fresh()->belongsToOrganization($organization));
        $this->assertFalse($member->fresh()->can('view', $brand));

        // A Marca não é afetada por quem sai: ela é da organização (SAD 20.2).
        $this->assertDatabaseHas('projects', [
            'id' => $brand->id,
            'organization_id' => $organization->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * Os dois eixos de permissão não se cruzam em direção nenhuma (SAD 20.7).
     */
    public function test_platform_staff_has_no_role_in_client_organizations(): void
    {
        $staff = User::factory()->create(['is_admin' => true]);
        $client = User::factory()->create();
        $organization = $this->organizationWith($client);

        $this->assertTrue($staff->isPlatformStaff());
        $this->assertNull($staff->roleIn($organization));
        $this->assertFalse($staff->can('view', $organization));
        $this->assertFalse($staff->can('manageBilling', $organization));
        $this->assertFalse($staff->can('view', $this->brandOf($organization)));

        // Nem o serviço reconhece staff como solicitante.
        $this->assertThrows(
            fn () => $this->membership()->transferOwnership($organization, $staff, $client),
            InsufficientOrganizationRoleException::class,
        );

        // E ser Owner da organização não concede staff da plataforma.
        $this->assertFalse($client->isPlatformStaff());
    }

    /**
     * Com mais de uma organização e nenhuma ativa, o backend não escolhe por
     * conta própria (SAD 20.5).
     */
    public function test_user_with_multiple_organizations_must_select_one(): void
    {
        $user = User::factory()->create(['firebase_uid' => 'uid-multi']);

        $this->organizationWith($user);
        $this->organizationWith($user);

        $this->getJson('/api/projects', $this->firebaseHeaders($user))
            ->assertStatus(409)
            ->assertJsonPath('error', 'organization_selection_required');
    }

    /**
     * O contexto ativo só aceita organização de que o usuário participa.
     */
    public function test_active_context_never_accepts_a_foreign_organization(): void
    {
        $user = User::factory()->create();
        $this->organizationWith($user);

        $foreign = Organization::factory()->withOwner()->create();

        $this->assertFalse($user->belongsToOrganization($foreign));
    }
}
