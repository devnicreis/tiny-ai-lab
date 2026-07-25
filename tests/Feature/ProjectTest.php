<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Autentica as requisições como o usuário informado, mockando o Firebase
     * para retornar claims correspondentes ao seu `firebase_uid`.
     *
     * @return array<string, string>
     */
    private function actingAsFirebaseUser(User $user): array
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

    public function test_user_can_list_own_projects(): void
    {
        $user = User::factory()->create(['firebase_uid' => 'uid-owner']);
        Project::factory()->count(3)->for($user)->create();
        Project::factory()->count(2)->create();

        $headers = $this->actingAsFirebaseUser($user);

        $this->getJson('/api/projects', $headers)
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('total', 3);
    }

    public function test_user_cannot_see_other_users_projects(): void
    {
        $user = User::factory()->create(['firebase_uid' => 'uid-owner']);
        $other = User::factory()->create(['firebase_uid' => 'uid-other']);
        $otherProject = Project::factory()->for($other)->create();

        $headers = $this->actingAsFirebaseUser($user);

        $this->getJson("/api/projects/{$otherProject->id}", $headers)
            ->assertForbidden();
    }

    public function test_user_can_create_project(): void
    {
        $user = User::factory()->create(['firebase_uid' => 'uid-owner']);
        $headers = $this->actingAsFirebaseUser($user);

        $payload = [
            'brand_name' => 'Tiny AI Lab',
            'segment' => 'SaaS',
            'website' => 'https://tinyailab.test',
            'description' => 'Plataforma de marketing com IA.',
            'competitors' => ['https://competitor-a.test', 'https://competitor-b.test'],
            'channels' => ['instagram', 'linkedin'],
        ];

        $this->postJson('/api/projects', $payload, $headers)
            ->assertCreated()
            ->assertJsonPath('brand_name', 'Tiny AI Lab')
            ->assertJsonPath('user_id', $user->id);

        $this->assertDatabaseHas('projects', [
            'brand_name' => 'Tiny AI Lab',
            'user_id' => $user->id,
        ]);
    }

    public function test_user_can_update_own_project(): void
    {
        $user = User::factory()->create(['firebase_uid' => 'uid-owner']);
        $project = Project::factory()->for($user)->create(['brand_name' => 'Antigo']);

        $headers = $this->actingAsFirebaseUser($user);

        $this->patchJson("/api/projects/{$project->id}", [
            'brand_name' => 'Novo Nome',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('brand_name', 'Novo Nome');

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'brand_name' => 'Novo Nome',
        ]);
    }

    public function test_user_cannot_update_other_users_project(): void
    {
        $user = User::factory()->create(['firebase_uid' => 'uid-owner']);
        $other = User::factory()->create(['firebase_uid' => 'uid-other']);
        $otherProject = Project::factory()->for($other)->create(['brand_name' => 'Alheio']);

        $headers = $this->actingAsFirebaseUser($user);

        $this->patchJson("/api/projects/{$otherProject->id}", [
            'brand_name' => 'Invasao',
        ], $headers)
            ->assertForbidden();

        $this->assertDatabaseHas('projects', [
            'id' => $otherProject->id,
            'brand_name' => 'Alheio',
        ]);
    }

    public function test_user_can_soft_delete_own_project(): void
    {
        $user = User::factory()->create(['firebase_uid' => 'uid-owner']);
        $project = Project::factory()->for($user)->create();

        $headers = $this->actingAsFirebaseUser($user);

        $this->deleteJson("/api/projects/{$project->id}", [], $headers)
            ->assertNoContent();

        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }
}
