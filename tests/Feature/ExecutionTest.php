<?php

namespace Tests\Feature;

use App\Events\ExecutionFailed;
use App\Events\ExecutionUpdated;
use App\Jobs\ProcessBriefingJob;
use App\Models\Execution;
use App\Models\Organization;
use App\Models\PlanLimit;
use App\Models\Project;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithOrganizations;
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
    use InteractsWithOrganizations, RefreshDatabase;

    private const EXECUTION_ID = 154;

    /**
     * Plano comercial da Seção 12.6, gravado em `organizations.plan`. Os nomes
     * antigos (`free`/`pro`/`enterprise`) pertenciam a `users.plan`, coluna que
     * não existe mais.
     */
    private const PLAN = 'copy_briefing';

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
        foreach (['copy_briefing', 'studio_visual', 'full_pass'] as $plan) {
            PlanLimit::create([
                'plan' => $plan,
                'action' => 'briefing_generation',
                'credits' => $credits,
            ]);
        }
    }

    /**
     * Organização com plano ativo, seu Admin e uma Brand.
     *
     * O plano é o primeiro dos três eixos da Seção 12.1: sem ele nenhum
     * pipeline roda, por mais crédito que exista em qualquer carteira
     * (SAD 12.18).
     *
     * @return array{0: User, 1: Project, 2: Organization}
     */
    private function organizationWithBrand(int $planCredits = 100, string $uid = 'uid-owner'): array
    {
        $user = User::factory()->create(['firebase_uid' => $uid]);

        $organization = $this->organizationWith($user, attributes: [
            'plan' => self::PLAN,
            'plan_credits_balance' => $planCredits,
            'plan_credits_used_this_month' => 0,
        ]);

        return [$user, $this->brandOf($organization), $organization];
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

    public function test_user_can_create_execution_when_organization_has_credits(): void
    {
        Queue::fake();
        $this->seedBriefingCost();

        [$user, $project, $organization] = $this->organizationWithBrand(planCredits: 100);

        $response = $this->postJson('/api/executions', [
            'project_id' => $project->id,
            'pipeline_type' => 'briefing_generation',
            'config' => ['language' => 'pt', 'objective' => 'lançamento'],
        ], $this->firebaseHeaders($user));

        $response->assertAccepted()
            ->assertJsonStructure(['execution_id', 'status'])
            ->assertJsonPath('status', 'queued');

        // O estado final pertence ao callback do n8n: o Controller só pode
        // deixar a Execution em `queued` (SAD 6.8).
        $this->assertDatabaseHas('executions', [
            'id' => $response->json('execution_id'),
            // `user_id` registra quem disparou — auditoria, nunca propriedade.
            'user_id' => $user->id,
            'project_id' => $project->id,
            'pipeline_type' => 'briefing_generation',
            'status' => 'queued',
            'credits_consumed' => self::BRIEFING_COST,
        ]);

        // Quem paga é a organização, nunca o usuário (SAD 12.1).
        $organization->refresh();
        $this->assertSame(100 - self::BRIEFING_COST, $organization->plan_credits_balance);
        $this->assertSame(self::BRIEFING_COST, $organization->plan_credits_used_this_month);
        // A carteira pessoal não foi tocada: as da organização bastaram
        // (SAD 12.3).
        $this->assertSame(0, $user->refresh()->purchased_credits_balance);

        Queue::assertPushed(
            ProcessBriefingJob::class,
            fn (ProcessBriefingJob $job) => $job->executionId === $response->json('execution_id'),
        );
    }

    public function test_insufficient_credits_returns_402(): void
    {
        Queue::fake();
        $this->seedBriefingCost();

        [$user, $project, $organization] = $this->organizationWithBrand(
            planCredits: self::BRIEFING_COST - 1,
            uid: 'uid-broke',
        );

        $this->postJson('/api/executions', [
            'project_id' => $project->id,
            'pipeline_type' => 'briefing_generation',
        ], $this->firebaseHeaders($user))
            ->assertStatus(402)
            ->assertJsonPath('error', 'insufficient_credits')
            ->assertJsonPath('cta', 'Buy AI Credits');

        // SAD 12.7: nem Execution, nem fila, nem débito.
        $this->assertDatabaseCount('executions', 0);
        $this->assertSame(self::BRIEFING_COST - 1, $organization->refresh()->plan_credits_balance);
        Queue::assertNothingPushed();
    }

    /**
     * Organização sem plano é bloqueio próprio, verificado ANTES do gating e do
     * saldo (SAD 12.18). Crédito na carteira pessoal não contorna a ausência de
     * contrato.
     */
    public function test_organization_without_plan_returns_402_with_subscription_cta(): void
    {
        Queue::fake();
        $this->seedBriefingCost();

        $user = User::factory()->create([
            'firebase_uid' => 'uid-no-plan',
            'purchased_credits_balance' => 10_000,
        ]);
        $organization = $this->organizationWith($user, attributes: ['plan' => null]);
        $project = $this->brandOf($organization);

        $this->postJson('/api/executions', [
            'project_id' => $project->id,
            'pipeline_type' => 'briefing_generation',
        ], $this->firebaseHeaders($user))
            ->assertStatus(402)
            ->assertJsonPath('error', 'no_active_plan')
            ->assertJsonPath('cta', 'Subscribe to a plan');

        $this->assertDatabaseCount('executions', 0);
        $this->assertSame(10_000, $user->refresh()->purchased_credits_balance);
        Queue::assertNothingPushed();
    }

    /**
     * Gating e saldo são eixos independentes com desfechos distintos: pipeline
     * fora do plano é 403 e nunca compartilha código nem CTA com o 402 de saldo
     * insuficiente (SAD 12.5).
     */
    public function test_pipeline_outside_the_plan_returns_403(): void
    {
        Queue::fake();
        $this->seedBriefingCost();

        // Saldo de sobra: o bloqueio vem do plano, não do saldo.
        [$user, $project, $organization] = $this->organizationWithBrand(
            planCredits: 10_000,
            uid: 'uid-gated',
        );

        PlanLimit::query()
            ->where('plan', $organization->plan)
            ->where('action', 'briefing_generation')
            ->update(['is_enabled' => false]);

        $this->postJson('/api/executions', [
            'project_id' => $project->id,
            'pipeline_type' => 'briefing_generation',
        ], $this->firebaseHeaders($user))
            ->assertStatus(403)
            ->assertJsonPath('error', 'feature_not_available');

        // Nem Execution, nem fila, nem débito — o gating precede tudo.
        $this->assertDatabaseCount('executions', 0);
        $this->assertSame(10_000, $organization->refresh()->plan_credits_balance);
        Queue::assertNothingPushed();
    }

    public function test_user_cannot_create_execution_in_a_brand_of_another_organization(): void
    {
        Queue::fake();
        $this->seedBriefingCost();

        [$user] = $this->organizationWithBrand();

        $foreignOwner = User::factory()->create(['firebase_uid' => 'uid-foreign']);
        $foreignProject = $this->brandOf($this->organizationWith($foreignOwner));

        $this->postJson('/api/executions', [
            'project_id' => $foreignProject->id,
            'pipeline_type' => 'briefing_generation',
        ], $this->firebaseHeaders($user))
            ->assertForbidden();

        $this->assertDatabaseCount('executions', 0);
        Queue::assertNothingPushed();
    }

    public function test_unknown_pipeline_type_is_rejected(): void
    {
        Queue::fake();
        $this->seedBriefingCost();

        [$user, $project] = $this->organizationWithBrand();

        $this->postJson('/api/executions', [
            'project_id' => $project->id,
            'pipeline_type' => 'drop_database',
        ], $this->firebaseHeaders($user))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pipeline_type');

        Queue::assertNothingPushed();
    }

    public function test_user_cannot_see_execution_of_another_organization(): void
    {
        [$user] = $this->organizationWithBrand();

        $foreignOwner = User::factory()->create(['firebase_uid' => 'uid-foreign']);
        $foreignProject = $this->brandOf($this->organizationWith($foreignOwner));
        $foreignExecution = Execution::factory()->create([
            'project_id' => $foreignProject->id,
            'user_id' => $foreignOwner->id,
        ]);

        $this->getJson(
            "/api/executions/{$foreignExecution->id}",
            $this->firebaseHeaders($user),
        )->assertForbidden();
    }

    /**
     * A Execution é da organização: o que um membro dispara, o outro acompanha
     * na mesma Brand (SAD 20.6).
     */
    public function test_member_sees_executions_created_by_another_member(): void
    {
        [$owner, $project, $organization] = $this->organizationWithBrand();
        $colleague = $this->memberOf($organization, attributes: ['firebase_uid' => 'uid-colleague']);

        Execution::factory()->create([
            'project_id' => $project->id,
            'user_id' => $owner->id,
        ]);

        $this->getJson(
            "/api/executions?project_id={$project->id}",
            $this->firebaseHeaders($colleague),
        )
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_execution_listing_is_scoped_to_the_brand_and_omits_the_result(): void
    {
        [$user, $activeBrand, $organization] = $this->organizationWithBrand();
        $activeBrand->update(['is_active' => true]);
        $otherBrand = $this->brandOf($organization, ['is_active' => false]);

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
            $this->firebaseHeaders($user),
        )->assertOk();

        $this->assertSame(1, $response->json('total'));
        $this->assertSame($activeBrand->id, $response->json('data.0.project_id'));

        // CLAUDE.md regra 11: nenhuma listagem carrega executions.result.
        $this->assertArrayNotHasKey('result', $response->json('data.0'));
        $this->assertArrayNotHasKey('config', $response->json('data.0'));
    }

    public function test_execution_listing_falls_back_to_the_active_brand(): void
    {
        [$user, $activeBrand] = $this->organizationWithBrand();
        $activeBrand->update(['is_active' => true]);

        Execution::factory()->count(2)->create([
            'project_id' => $activeBrand->id,
            'user_id' => $user->id,
        ]);

        $this->getJson('/api/executions', $this->firebaseHeaders($user))
            ->assertOk()
            ->assertJsonPath('total', 2);
    }

    public function test_execution_listing_rejects_a_brand_of_another_organization(): void
    {
        [$user] = $this->organizationWithBrand();

        $foreignOwner = User::factory()->create(['firebase_uid' => 'uid-foreign']);
        $foreignBrand = $this->brandOf($this->organizationWith($foreignOwner));

        $this->getJson(
            "/api/executions?project_id={$foreignBrand->id}",
            $this->firebaseHeaders($user),
        )->assertForbidden();
    }
}
