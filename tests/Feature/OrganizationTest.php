<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOrganizations;
use Tests\TestCase;

/**
 * Papéis, invariantes e perda de acesso (SAD 20.4 e 20.6).
 *
 * A matriz tem dois papéis e nenhuma capacidade é inferida: faturamento,
 * participação e existência da organização são de Admin; Marcas e execuções são
 * de qualquer membro.
 */
class OrganizationTest extends TestCase
{
    use InteractsWithOrganizations, RefreshDatabase;

    /**
     * Faturamento é exclusivo de Admin (SAD 20.4).
     *
     * Um Member continua podendo trabalhar — criar Marcas, rodar execuções — e
     * comprar créditos para a **própria carteira**, que é dele e não da
     * organização (SAD 12.2).
     */
    public function test_member_cannot_manage_billing(): void
    {
        $admin = User::factory()->create();
        $organization = $this->organizationWith($admin, OrganizationRole::Admin);
        $member = $this->memberOf($organization, OrganizationRole::Member);

        $this->assertTrue($admin->can('manageBilling', $organization));
        $this->assertFalse($member->can('manageBilling', $organization));

        $this->assertFalse($member->can('purchaseForOrganization', $organization));
        $this->assertFalse($member->can('manageMembers', $organization));
        $this->assertFalse($member->can('delete', $organization));
        $this->assertFalse($member->can('update', $organization));

        // O que o Member pode: enxergar a organização e gerenciar Marcas.
        $this->assertTrue($member->can('view', $organization));
        $this->assertTrue($member->can('update', $this->brandOf($organization)));
    }

    /**
     * Sair da organização encerra o acesso na requisição seguinte (SAD 20.5).
     *
     * A sessão ainda aponta para a organização, e é justamente por isso que a
     * participação é reconferida a cada acesso em vez de confiar no contexto
     * gravado.
     */
    public function test_removed_member_loses_access_immediately(): void
    {
        $admin = User::factory()->create(['firebase_uid' => 'uid-admin']);
        $organization = $this->organizationWith($admin, OrganizationRole::Admin);
        $member = $this->memberOf($organization, OrganizationRole::Member, [
            'firebase_uid' => 'uid-member',
        ]);

        $brand = $this->brandOf($organization);

        $headers = $this->firebaseHeaders($member);

        $this->getJson("/api/projects/{$brand->id}", $headers)->assertOk();

        $organization->users()->detach($member->id);

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
     * Uma organização nunca fica sem Admin (SAD 20.4, CLAUDE.md regra 32).
     *
     * Vale para remoção e para rebaixamento, e vale igualmente quando o alvo é
     * o próprio solicitante: sair é uma remoção como outra qualquer.
     */
    public function test_cannot_remove_last_admin_from_organization(): void
    {
        $admin = User::factory()->create();
        $organization = $this->organizationWith($admin, OrganizationRole::Admin);
        $member = $this->memberOf($organization, OrganizationRole::Member);

        $this->assertTrue($organization->isLastAdmin($admin));

        // Nem outro Admin poderia — não existe outro — nem ele próprio.
        $this->assertFalse($admin->can('removeMember', [$organization, $admin]));
        $this->assertFalse($admin->can('changeRole', [$organization, $admin, OrganizationRole::Member]));

        // O Member, sim, pode ser removido: não é Admin.
        $this->assertTrue($admin->can('removeMember', [$organization, $member]));

        // Com um segundo Admin o invariante deixa de ser tocado.
        $this->assertTrue($admin->can('changeRole', [$organization, $member, OrganizationRole::Admin]));

        $organization->users()->updateExistingPivot($member->id, [
            'role' => OrganizationRole::Admin->value,
        ]);
        $organization->unsetRelation('users');

        $this->assertFalse($organization->isLastAdmin($admin));
        $this->assertTrue($admin->can('removeMember', [$organization, $admin]));
        $this->assertTrue($admin->can('changeRole', [$organization, $admin, OrganizationRole::Member]));
    }

    /**
     * Os dois eixos de permissão não se cruzam em direção nenhuma (SAD 20.7).
     */
    public function test_platform_staff_has_no_role_in_client_organizations(): void
    {
        $staff = User::factory()->create(['is_admin' => true]);
        $client = User::factory()->create();
        $organization = $this->organizationWith($client, OrganizationRole::Admin);

        $this->assertTrue($staff->isPlatformStaff());
        $this->assertNull($staff->roleIn($organization));
        $this->assertFalse($staff->can('view', $organization));
        $this->assertFalse($staff->can('manageBilling', $organization));
        $this->assertFalse($staff->can('view', $this->brandOf($organization)));

        // E ser Admin da organização não concede staff da plataforma.
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

        $foreign = Organization::factory()->create();

        $this->assertFalse($user->belongsToOrganization($foreign));
    }
}
