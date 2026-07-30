<?php

namespace Tests\Feature;

use App\Events\ExecutionFailed;
use App\Events\ExecutionUpdated;
use App\Jobs\ProcessBriefingJob;
use App\Models\Execution;
use App\Models\PlanLimit;
use App\Models\Project;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Contrato dos eventos de broadcast de uma Execution (SAD 9.6 e 9.7) e da API
 * de execuções (SAD 6.1).
 *
 * O WebSocket apenas sinaliza mudança de estado: `result` e `config` são
 * carregados depois, por consulta autorizada ao Laravel.
 */
class ExecutionTest extends TestCase
{
    use RefreshDatabase;

    private const EXECUTION_ID = 154;

    /**
     * Custo de `briefing_generation` segundo o PlanLimitSeeder (SAD 12.3).
     */
    private const BRIEFING_COST = 5;

    /**
     * Registra em `plan_limits` o custo do pipeline usado nos testes.
     *
     * O `PlanLimitSeeder` completo não roda aqui: ele grava `credits = null`
     * para o plano enterprise e a coluna permanece NOT NULL no SQLite — a
     * migration que a torna nullable é exclusiva do PostgreSQL. Gravar apenas a
     * linha necessária prova o mesmo ponto: o débito vem da tabela, nunca do
     * código (CLAUDE.md regra 9).
     */
    private function seedBriefingCost(int $credits = self::BRIEFING_COST): void
    {
        foreach (['free', 'pro', 'enterprise'] as $plan) {
            PlanLimit::create([
                'plan' => $plan,
                'action' => 'briefing_generation',
                'credits' => $credits,
            ]);
        }
    }

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

    private function execution(array $attributes = []): Execution
    {
        $execution = Execution::factory()->make(array_merge([
            'project_id' => 8,
            'user_id' => 12,
            'pipeline_type' => 'brand_research',
            'config' => ['language' => 'pt'],
        ], $attributes));

        $execution->forceFill(['id' => self::EXECUTION_ID]);

        return $execution;
    }

    public function test_execution_updated_broadcasts_on_the_private_execution_channel(): void
    {
        $channels = (new ExecutionUpdated($this->execution()))->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-execution.'.self::EXECUTION_ID, (string) $channels[0]);
    }

    public function test_execution_updated_broadcasts_only_state_metadata(): void
    {
        $execution = $this->execution([
            'status' => 'completed',
            'result' => ['diagnosis' => ['segredo do resultado']],
            'completed_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = (new ExecutionUpdated($execution))->broadcastWith();

        $this->assertSame(
            ['execution_id', 'status', 'pipeline_type', 'updated_at'],
            array_keys($payload),
        );
        $this->assertSame(self::EXECUTION_ID, $payload['execution_id']);
        $this->assertSame('completed', $payload['status']);
        $this->assertSame('brand_research', $payload['pipeline_type']);
        $this->assertSame($execution->updated_at->toIso8601String(), $payload['updated_at']);
        $this->assertStringNotContainsString('segredo do resultado', json_encode($payload));
    }

    public function test_execution_failed_broadcasts_on_the_private_execution_channel(): void
    {
        $channels = (new ExecutionFailed($this->execution()))->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-execution.'.self::EXECUTION_ID, (string) $channels[0]);
    }

    public function test_execution_failed_broadcasts_only_the_error_metadata(): void
    {
        $execution = $this->execution([
            'status' => 'failed',
            'error_message' => 'Pipeline timeout',
            'failed_at' => now(),
        ]);

        $payload = (new ExecutionFailed($execution))->broadcastWith();

        $this->assertSame(
            ['execution_id', 'status', 'pipeline_type', 'error_message', 'failed_at'],
            array_keys($payload),
        );
        $this->assertSame(self::EXECUTION_ID, $payload['execution_id']);
        $this->assertSame('failed', $payload['status']);
        $this->assertSame('brand_research', $payload['pipeline_type']);
        $this->assertSame('Pipeline timeout', $payload['error_message']);
        $this->assertSame($execution->failed_at->toIso8601String(), $payload['failed_at']);
    }

    public function test_user_can_create_execution_when_has_credits(): void
    {
        Queue::fake();
        $this->seedBriefingCost();

        $user = User::factory()->create([
            'firebase_uid' => 'uid-owner',
            'credits_balance' => 100,
            'credits_used_this_month' => 0,
        ]);
        $project = Project::factory()->for($user)->create();

        $response = $this->postJson('/api/executions', [
            'project_id' => $project->id,
            'pipeline_type' => 'briefing_generation',
            'config' => ['language' => 'pt', 'objective' => 'lançamento'],
        ], $this->actingAsFirebaseUser($user));

        $response->assertAccepted()
            ->assertJsonStructure(['execution_id', 'status'])
            ->assertJsonPath('status', 'queued');

        // O estado final pertence ao callback do n8n: o Controller só pode
        // deixar a Execution em `queued` (SAD 6.8).
        $this->assertDatabaseHas('executions', [
            'id' => $response->json('execution_id'),
            'user_id' => $user->id,
            'project_id' => $project->id,
            'pipeline_type' => 'briefing_generation',
            'status' => 'queued',
            'credits_consumed' => self::BRIEFING_COST,
        ]);

        $user->refresh();
        $this->assertSame(100 - self::BRIEFING_COST, $user->credits_balance);
        $this->assertSame(self::BRIEFING_COST, $user->credits_used_this_month);

        Queue::assertPushed(
            ProcessBriefingJob::class,
            fn (ProcessBriefingJob $job) => $job->executionId === $response->json('execution_id'),
        );
    }

    public function test_insufficient_credits_returns_402(): void
    {
        Queue::fake();
        $this->seedBriefingCost();

        $user = User::factory()->create([
            'firebase_uid' => 'uid-broke',
            'credits_balance' => self::BRIEFING_COST - 1,
        ]);
        $project = Project::factory()->for($user)->create();

        $this->postJson('/api/executions', [
            'project_id' => $project->id,
            'pipeline_type' => 'briefing_generation',
        ], $this->actingAsFirebaseUser($user))
            ->assertStatus(402)
            ->assertJsonPath('error', 'insufficient_credits')
            ->assertJsonPath('cta', 'Buy AI Credits');

        // SAD 12.7: nem Execution, nem fila, nem débito.
        $this->assertDatabaseCount('executions', 0);
        $this->assertSame(self::BRIEFING_COST - 1, $user->refresh()->credits_balance);
        Queue::assertNothingPushed();
    }

    public function test_user_cannot_create_execution_in_another_users_brand(): void
    {
        Queue::fake();
        $this->seedBriefingCost();

        $user = User::factory()->create(['firebase_uid' => 'uid-owner', 'credits_balance' => 100]);
        $other = User::factory()->create(['firebase_uid' => 'uid-other']);
        $foreignProject = Project::factory()->for($other)->create();

        $this->postJson('/api/executions', [
            'project_id' => $foreignProject->id,
            'pipeline_type' => 'briefing_generation',
        ], $this->actingAsFirebaseUser($user))
            ->assertForbidden();

        $this->assertDatabaseCount('executions', 0);
        Queue::assertNothingPushed();
    }

    public function test_unknown_pipeline_type_is_rejected(): void
    {
        Queue::fake();
        $this->seedBriefingCost();

        $user = User::factory()->create(['firebase_uid' => 'uid-owner', 'credits_balance' => 100]);
        $project = Project::factory()->for($user)->create();

        $this->postJson('/api/executions', [
            'project_id' => $project->id,
            'pipeline_type' => 'drop_database',
        ], $this->actingAsFirebaseUser($user))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pipeline_type');

        Queue::assertNothingPushed();
    }

    public function test_user_cannot_see_other_users_execution(): void
    {
        $user = User::factory()->create(['firebase_uid' => 'uid-owner']);
        $other = User::factory()->create(['firebase_uid' => 'uid-other']);

        $foreignProject = Project::factory()->for($other)->create();
        $foreignExecution = Execution::factory()->create([
            'project_id' => $foreignProject->id,
            'user_id' => $other->id,
        ]);

        $this->getJson(
            "/api/executions/{$foreignExecution->id}",
            $this->actingAsFirebaseUser($user),
        )->assertForbidden();
    }

    public function test_execution_listing_is_scoped_to_the_brand_and_omits_the_result(): void
    {
        $user = User::factory()->create(['firebase_uid' => 'uid-owner']);
        $activeBrand = Project::factory()->for($user)->create(['is_active' => true]);
        $otherBrand = Project::factory()->for($user)->create(['is_active' => false]);

        Execution::factory()->completed()->create([
            'project_id' => $activeBrand->id,
            'user_id' => $user->id,
        ]);
        Execution::factory()->create([
            'project_id' => $otherBrand->id,
            'user_id' => $user->id,
        ]);

        $response = $this->getJson(
            "/api/executions?project_id={$activeBrand->id}",
            $this->actingAsFirebaseUser($user),
        )->assertOk();

        $this->assertSame(1, $response->json('total'));
        $this->assertSame($activeBrand->id, $response->json('data.0.project_id'));

        // CLAUDE.md regra 11: nenhuma listagem carrega executions.result.
        $this->assertArrayNotHasKey('result', $response->json('data.0'));
        $this->assertArrayNotHasKey('config', $response->json('data.0'));
    }

    public function test_execution_listing_falls_back_to_the_active_brand(): void
    {
        $user = User::factory()->create(['firebase_uid' => 'uid-owner']);
        $activeBrand = Project::factory()->for($user)->create(['is_active' => true]);

        Execution::factory()->count(2)->create([
            'project_id' => $activeBrand->id,
            'user_id' => $user->id,
        ]);

        $this->getJson('/api/executions', $this->actingAsFirebaseUser($user))
            ->assertOk()
            ->assertJsonPath('total', 2);
    }

    public function test_execution_listing_rejects_another_users_brand(): void
    {
        $user = User::factory()->create(['firebase_uid' => 'uid-owner']);
        $other = User::factory()->create(['firebase_uid' => 'uid-other']);
        $foreignBrand = Project::factory()->for($other)->create();

        $this->getJson(
            "/api/executions?project_id={$foreignBrand->id}",
            $this->actingAsFirebaseUser($user),
        )->assertForbidden();
    }
}
