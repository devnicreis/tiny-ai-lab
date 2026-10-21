<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOrganizations;
use Tests\TestCase;

/**
 * CRUD das Brands sob o modelo de organizações (SAD 20.2 e 20.6).
 *
 * O que estes testes provam é o deslocamento do critério de isolamento: a Brand
 * é vista por quem participa da **organização dona**, e criada, editada e
 * excluída por quem nela é Owner ou Admin (SAD 5.9) — `projects.user_id` não
 * existe mais como propriedade.
 */
class ProjectTest extends TestCase
{
    use InteractsWithOrganizations, RefreshDatabase;

    public function test_user_can_list_projects_of_the_active_organization(): void
    {
        $user = User::factory()->create(['firebase_uid' => 'uid-member']);
        $organization = $this->organizationWith($user);

        Project::factory()->count(3)->for($organization)->create();
        // Marcas de outra organização: invisíveis, ainda que criadas no mesmo
        // banco (SAD 20.6).
        Project::factory()->count(2)->create();

        $this->getJson('/api/projects', $this->firebaseHeaders($user))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('total', 3);
    }

    /**
     * O isolamento é entre organizações, não entre usuários: quem não participa
     * da organização dona não enxerga a Brand nem por link direto (SAD 20.6).
     */
    public function test_user_cannot_see_projects_from_other_organization(): void
    {
        $user = User::factory()->create(['firebase_uid' => 'uid-member']);
        $this->organizationWith($user);

        $foreignOwner = User::factory()->create(['firebase_uid' => 'uid-foreign']);
        $foreignOrganization = $this->organizationWith($foreignOwner);
        $foreignProject = $this->brandOf($foreignOrganization);

        $headers = $this->firebaseHeaders($user);

        $this->getJson("/api/projects/{$foreignProject->id}", $headers)
            ->assertForbidden();

        // Nem por listagem: o escopo é a organização ativa.
        $this->getJson('/api/projects', $headers)
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    /**
     * O Member enxerga as Marcas e roda as ferramentas de IA nelas, mas não as
     * cria, edita nem exclui — a Brand Memory é de Owner e Admin (SAD 5.9,
     * ADR-010).
     *
     * > 🔁 SUPERSEDED (ADR-010): substitui o teste "Member cria marca".
     */
    public function test_member_cannot_create_or_edit_brand(): void
    {
        $owner = User::factory()->create(['firebase_uid' => 'uid-owner']);
        $organization = $this->organizationWith($owner);
        $member = $this->memberOf($organization, OrganizationRole::Member, [
            'firebase_uid' => 'uid-member',
        ]);
        $brand = $this->brandOf($organization, ['brand_name' => 'Original']);

        $headers = $this->firebaseHeaders($member);

        $this->postJson('/api/projects', $this->brandPayload(), $headers)
            ->assertForbidden();

        $this->patchJson("/api/projects/{$brand->id}", ['brand_name' => 'Changed'], $headers)
            ->assertForbidden();

        $this->deleteJson("/api/projects/{$brand->id}", [], $headers)
            ->assertForbidden();

        // Ver continua sendo de qualquer membro.
        $this->getJson("/api/projects/{$brand->id}", $headers)
            ->assertOk()
            ->assertJsonPath('brand_name', 'Original');

        $this->assertDatabaseMissing('projects', ['brand_name' => 'Tiny AI Lab']);
        $this->assertDatabaseHas('projects', [
            'id' => $brand->id,
            'brand_name' => 'Original',
            'deleted_at' => null,
        ]);
    }

    /**
     * O Admin gerencia o trabalho: cria e edita Marcas e vê os relatórios de
     * consumo — sem tocar faturamento (SAD 20.4).
     */
    public function test_admin_creates_brand_and_views_credit_usage(): void
    {
        $owner = User::factory()->create(['firebase_uid' => 'uid-owner']);
        $organization = $this->organizationWith($owner);
        $admin = $this->adminOf($organization, ['firebase_uid' => 'uid-admin']);
        $member = $this->memberOf($organization);

        $headers = $this->firebaseHeaders($admin);

        $projectId = $this->postJson('/api/projects', $this->brandPayload(), $headers)
            ->assertCreated()
            ->assertJsonPath('brand_name', 'Tiny AI Lab')
            ->assertJsonPath('organization_id', $organization->id)
            // Provenance de criação, nunca propriedade (SAD 20.2).
            ->assertJsonPath('created_by_user_id', $admin->id)
            ->json('id');

        $this->patchJson("/api/projects/{$projectId}", ['brand_name' => 'Renamed'], $headers)
            ->assertOk()
            ->assertJsonPath('brand_name', 'Renamed');

        $this->assertDatabaseHas('projects', [
            'id' => $projectId,
            'brand_name' => 'Renamed',
            'organization_id' => $organization->id,
            'created_by_user_id' => $admin->id,
        ]);

        $this->assertTrue($admin->can('viewCreditUsage', $organization));
        $this->assertTrue($owner->can('viewCreditUsage', $organization));
        $this->assertFalse($member->can('viewCreditUsage', $organization));

        // Gerenciar Marcas não abre faturamento.
        $this->assertFalse($admin->can('manageBilling', $organization));
    }

    public function test_user_can_update_project_of_own_organization(): void
    {
        $user = User::factory()->create(['firebase_uid' => 'uid-member']);
        $organization = $this->organizationWith($user);
        $project = $this->brandOf($organization, ['brand_name' => 'Antigo']);

        $this->patchJson("/api/projects/{$project->id}", [
            'brand_name' => 'Novo Nome',
        ], $this->firebaseHeaders($user))
            ->assertOk()
            ->assertJsonPath('brand_name', 'Novo Nome');

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'brand_name' => 'Novo Nome',
        ]);
    }

    public function test_user_cannot_update_project_of_other_organization(): void
    {
        $user = User::factory()->create(['firebase_uid' => 'uid-member']);
        $this->organizationWith($user);

        $foreignOwner = User::factory()->create(['firebase_uid' => 'uid-foreign']);
        $foreignProject = $this->brandOf(
            $this->organizationWith($foreignOwner),
            ['brand_name' => 'Alheio'],
        );

        $this->patchJson("/api/projects/{$foreignProject->id}", [
            'brand_name' => 'Invasao',
        ], $this->firebaseHeaders($user))
            ->assertForbidden();

        $this->assertDatabaseHas('projects', [
            'id' => $foreignProject->id,
            'brand_name' => 'Alheio',
        ]);
    }

    public function test_user_can_soft_delete_project_of_own_organization(): void
    {
        $user = User::factory()->create(['firebase_uid' => 'uid-member']);
        $project = $this->brandOf($this->organizationWith($user));

        $this->deleteJson("/api/projects/{$project->id}", [], $this->firebaseHeaders($user))
            ->assertNoContent();

        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }

    /**
     * Sem organização nenhuma não há contexto de domínio a servir: nada existe
     * fora de uma organização (SAD 20.2).
     */
    public function test_user_without_organization_has_no_domain_context(): void
    {
        $user = User::factory()->create(['firebase_uid' => 'uid-orphan']);

        $this->getJson('/api/projects', $this->firebaseHeaders($user))
            ->assertStatus(409)
            ->assertJsonPath('error', 'organization_required');
    }

    /**
     * @return array<string, mixed>
     */
    private function brandPayload(): array
    {
        return [
            'brand_name' => 'Tiny AI Lab',
            'segment' => 'SaaS',
            'website' => 'https://tinyailab.test',
            'description' => 'Plataforma de marketing com IA.',
            'competitors' => ['https://competitor-a.test', 'https://competitor-b.test'],
            'channels' => ['instagram', 'linkedin'],
        ];
    }
}
