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
 * é vista, editada e excluída por quem participa da **organização dona** — e
 * `projects.user_id` não existe mais como propriedade.
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
     * Criar Marcas é capacidade de qualquer membro, Admin ou Member (SAD 20.4).
     */
    public function test_member_can_create_project_in_the_active_organization(): void
    {
        $admin = User::factory()->create(['firebase_uid' => 'uid-admin']);
        $organization = $this->organizationWith($admin);

        $member = $this->memberOf($organization, OrganizationRole::Member, [
            'firebase_uid' => 'uid-member',
        ]);

        $payload = [
            'brand_name' => 'Tiny AI Lab',
            'segment' => 'SaaS',
            'website' => 'https://tinyailab.test',
            'description' => 'Plataforma de marketing com IA.',
            'competitors' => ['https://competitor-a.test', 'https://competitor-b.test'],
            'channels' => ['instagram', 'linkedin'],
        ];

        $this->postJson('/api/projects', $payload, $this->firebaseHeaders($member))
            ->assertCreated()
            ->assertJsonPath('brand_name', 'Tiny AI Lab')
            ->assertJsonPath('organization_id', $organization->id)
            // Provenance de criação, nunca propriedade (SAD 20.2).
            ->assertJsonPath('created_by_user_id', $member->id);

        $this->assertDatabaseHas('projects', [
            'brand_name' => 'Tiny AI Lab',
            'organization_id' => $organization->id,
            'created_by_user_id' => $member->id,
        ]);
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
}
