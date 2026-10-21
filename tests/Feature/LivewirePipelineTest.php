<?php

namespace Tests\Feature;

use App\Jobs\ProcessBriefingJob;
use App\Livewire\BriefingForm;
use App\Livewire\ExecutionStatus;
use App\Livewire\ResultDashboard;
use App\Models\Execution;
use App\Models\Organization;
use App\Models\PlanLimit;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithOrganizations;
use Tests\TestCase;

/**
 * Contrato dos componentes Livewire do pipeline (SAD M).
 *
 * O que se prova aqui é a fronteira: os componentes exibem e submetem, mas o
 * débito de crédito, o despacho do Job e o recorte por dono continuam sendo do
 * backend.
 */
class LivewirePipelineTest extends TestCase
{
    use InteractsWithOrganizations, RefreshDatabase;

    private const BRIEFING_COST = 5;

    /**
     * Plano comercial da Seção 12.6, gravado em `organizations.plan`. Os nomes
     * antigos (`free`/`pro`/`enterprise`) pertenciam a `users.plan`, coluna que
     * não existe mais.
     */
    private const PLAN = 'copy_briefing';

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
     * Organização com plano, seu Admin e a Brand ativa.
     *
     * O saldo é da **organização**: o usuário entra na conta apenas pela
     * carteira pessoal, que estes testes deixam zerada (SAD 12.1 e 12.2).
     *
     * @return array{0: User, 1: Project, 2: Organization}
     */
    private function userWithBrand(int $balance = 100): array
    {
        $user = User::factory()->create();

        $organization = $this->organizationWith($user, attributes: [
            'plan' => self::PLAN,
            'plan_credits_balance' => $balance,
        ]);

        return [$user, $this->brandOf($organization, ['is_active' => true]), $organization];
    }

    /**
     * @return array<string, string>
     */
    private function briefingInput(int $projectId): array
    {
        return [
            'project_id' => $projectId,
            'pipeline_type' => 'briefing_generation',
            'objective' => 'Estruturar a campanha de lançamento do produto novo.',
            'target_audience' => 'Gestores de marketing B2B',
            'tone' => 'Consultivo',
            'language' => 'pt-BR',
            'notes' => '',
        ];
    }

    public function test_briefing_form_creates_execution_debits_credits_and_queues_the_job(): void
    {
        Queue::fake();
        $this->seedBriefingCost();

        [$user, $brand, $organization] = $this->userWithBrand(balance: 40);

        Livewire::actingAs($user)
            ->test(BriefingForm::class)
            ->set($this->briefingInput($brand->id))
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('result', 1));

        $execution = Execution::query()->sole();

        $this->assertSame('queued', $execution->status);
        $this->assertSame($brand->id, $execution->project_id);
        $this->assertSame($user->id, $execution->user_id);
        $this->assertSame(self::BRIEFING_COST, $execution->credits_consumed);
        $this->assertSame(
            'Estruturar a campanha de lançamento do produto novo.',
            $execution->config['inputs']['objective'],
        );

        $this->assertSame(40 - self::BRIEFING_COST, $organization->fresh()->plan_credits_balance);

        Queue::assertPushed(ProcessBriefingJob::class);
    }

    public function test_briefing_form_shows_upgrade_message_when_balance_is_insufficient(): void
    {
        Queue::fake();
        $this->seedBriefingCost();

        [$user, $brand, $organization] = $this->userWithBrand(balance: 1);

        Livewire::actingAs($user)
            ->test(BriefingForm::class)
            ->set($this->briefingInput($brand->id))
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('upgradeMessage', fn (?string $message) => $message !== null
                && str_contains($message, 'Insufficient credits'));

        $this->assertDatabaseCount('executions', 0);
        $this->assertSame(1, $organization->fresh()->plan_credits_balance);

        Queue::assertNothingPushed();
    }

    /**
     * A mensagem do gating nunca cita saldo: comprar créditos não destravaria
     * um pipeline fora do plano (SAD 12.5).
     */
    public function test_briefing_form_shows_upgrade_message_when_pipeline_is_outside_the_plan(): void
    {
        Queue::fake();
        $this->seedBriefingCost();

        [$user, $brand, $organization] = $this->userWithBrand(balance: 10_000);

        PlanLimit::query()
            ->where('plan', $organization->plan)
            ->where('action', 'briefing_generation')
            ->update(['is_enabled' => false]);

        Livewire::actingAs($user)
            ->test(BriefingForm::class)
            ->set($this->briefingInput($brand->id))
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('upgradeMessage', fn (?string $message) => $message !== null
                && str_contains($message, 'not available on your current plan')
                && ! str_contains($message, 'credits'));

        $this->assertDatabaseCount('executions', 0);
        $this->assertSame(10_000, $organization->fresh()->plan_credits_balance);

        Queue::assertNothingPushed();
    }

    public function test_briefing_form_rejects_a_brand_from_another_organization(): void
    {
        Queue::fake();
        $this->seedBriefingCost();

        [$user] = $this->userWithBrand();
        // Brand de outra organização: o `exists` da regra é escopado à
        // organização ativa e a rejeita antes de qualquer débito (SAD 20.6).
        $foreignBrand = Project::factory()->create();

        Livewire::actingAs($user)
            ->test(BriefingForm::class)
            ->set($this->briefingInput($foreignBrand->id))
            ->call('submit')
            ->assertHasErrors('project_id');

        $this->assertDatabaseCount('executions', 0);
        Queue::assertNothingPushed();
    }

    public function test_execution_status_updates_from_the_broadcast_metadata_only(): void
    {
        [$user, $brand] = $this->userWithBrand();

        $execution = Execution::factory()->for($brand)->create([
            'user_id' => $user->id,
            'status' => 'processing',
            'pipeline_type' => 'briefing_generation',
        ]);

        Livewire::actingAs($user)
            ->test(ExecutionStatus::class, ['executionId' => $execution->id])
            ->assertSet('status', 'processing')
            ->assertSet('pipelineType', 'briefing_generation')
            ->call('onExecutionUpdated', ['status' => 'completed', 'execution_id' => $execution->id])
            ->assertSet('status', 'completed')
            ->assertDispatched('execution-finished', executionId: $execution->id);
    }

    public function test_execution_status_never_serves_an_execution_from_another_organization(): void
    {
        [$user] = $this->userWithBrand();
        $foreign = Execution::factory()->create(['status' => 'processing']);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($user)->test(ExecutionStatus::class, ['executionId' => $foreign->id]);
    }

    public function test_result_dashboard_renders_the_result_sections(): void
    {
        [$user, $brand] = $this->userWithBrand();

        $execution = Execution::factory()->for($brand)->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'result' => [
                'diagnosis' => 'Marca com posicionamento difuso.',
                'calendar' => [['day' => 1, 'theme' => 'Lançamento']],
                'copies' => ['Copy de abertura'],
                'scripts' => ['Roteiro do reels'],
            ],
            'completed_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(ResultDashboard::class, ['executionId' => $execution->id])
            ->assertSee('diagnosis')
            ->assertSee('Marca com posicionamento difuso.')
            ->call('selectSection', 'copies')
            ->assertSet('activeSection', 'copies')
            ->assertSee('Copy de abertura');
    }

    public function test_briefing_page_renders_the_form_inside_the_app_shell(): void
    {
        [$user] = $this->userWithBrand();

        // Rota web: sessão (`auth.web`), nunca Bearer (Sessão 3.1).
        $this->actingAs($user)->get('/briefing')
            ->assertOk()
            ->assertSee('Novo Briefing')
            ->assertSee('Tiny AI Lab');
    }

    public function test_result_page_renders_status_and_lazy_result_for_the_owner(): void
    {
        [$user, $brand] = $this->userWithBrand();

        $execution = Execution::factory()->for($brand)->create([
            'user_id' => $user->id,
            'status' => 'processing',
        ]);

        $response = $this->actingAs($user)->get('/result/'.$execution->id);

        $response->assertOk()->assertSee('Execução #'.$execution->id);

        // O `result` é lazy: nada do JSONB pode aparecer na resposta inicial
        // (SAD M.9). O placeholder é o que ocupa a área.
        $response->assertDontSee('diagnosis');
    }

    public function test_result_page_is_denied_to_a_user_outside_the_organization(): void
    {
        [$user] = $this->userWithBrand();
        $foreign = Execution::factory()->create();

        $this->actingAs($user)->get('/result/'.$foreign->id)
            ->assertForbidden();
    }

    public function test_result_dashboard_holds_no_result_while_the_execution_is_not_completed(): void
    {
        [$user, $brand] = $this->userWithBrand();

        $execution = Execution::factory()->for($brand)->create([
            'user_id' => $user->id,
            'status' => 'processing',
            'result' => null,
        ]);

        Livewire::actingAs($user)
            ->test(ResultDashboard::class, ['executionId' => $execution->id])
            ->assertSet('activeSection', '')
            ->assertSee('quando a execução for concluída');
    }
}
