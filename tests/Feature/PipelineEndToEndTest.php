<?php

namespace Tests\Feature;

use App\Events\ExecutionFailed;
use App\Events\ExecutionUpdated;
use App\Exceptions\N8nConnectionException;
use App\Jobs\ProcessBriefingJob;
use App\Models\PlanLimit;
use App\Models\Project;
use App\Models\User;
use App\Services\FirebaseService;
use App\Services\N8nService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Pipeline completo, ponta a ponta, sem o n8n real (SAD 6.1 e 6.3).
 *
 * Os testes das sessões anteriores cobrem cada peça isoladamente — Controller,
 * Job, Service, callback. O que falta, e é o objetivo deste arquivo, é provar
 * que as peças se encaixam na ordem documentada em SAD 6.1 (etapas 1 a 11) e
 * que a fronteira de estados se mantém ao longo de todo o trajeto:
 *
 *   HTTP → `queued` → Worker → `processing` → callback do n8n → `completed`
 *
 * O n8n é substituído por `Http::fake()` na saída e por uma requisição ao
 * próprio endpoint de callback na volta — nenhuma etapa é encurtada: o Job é
 * realmente executado e o webhook realmente atravessa o middleware
 * `verify.n8n`. `Http::preventStrayRequests()` garante que nenhuma chamada
 * escape para a rede.
 */
class PipelineEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private const N8N_URL = 'https://n8n.tinyailab.test/webhook/pipeline';

    private const N8N_SECRET = 'segredo-compartilhado-de-ponta-a-ponta';

    private const APP_URL = 'https://app.tinyailab.test';

    private const CALLBACK_ENDPOINT = '/api/webhook/n8n/result';

    private const PIPELINE = 'briefing_generation';

    /**
     * Custo de `briefing_generation` segundo o PlanLimitSeeder (SAD 12.3).
     */
    private const BRIEFING_COST = 5;

    private const INITIAL_BALANCE = 100;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.n8n.url' => self::N8N_URL,
            // O mesmo segredo compartilhado vale na ida (header enviado pelo
            // N8nService) e na volta (header validado pelo `verify.n8n`) — SAD 7.5.
            'services.n8n.secret' => self::N8N_SECRET,
            'app.url' => self::APP_URL,
        ]);

        Http::preventStrayRequests();

        $this->seedBriefingCost();
    }

    /**
     * Registra em `plan_limits` apenas o custo do pipeline exercitado aqui.
     *
     * O `PlanLimitSeeder` completo não roda no SQLite: ele grava `credits = null`
     * para o plano enterprise e a migration que torna a coluna nullable é
     * exclusiva do PostgreSQL. O ponto do teste é o mesmo — o débito vem da
     * tabela, nunca do código (CLAUDE.md regra 9).
     */
    private function seedBriefingCost(): void
    {
        foreach (['free', 'pro', 'enterprise'] as $plan) {
            PlanLimit::create([
                'plan' => $plan,
                'action' => self::PIPELINE,
                'credits' => self::BRIEFING_COST,
            ]);
        }
    }

    /**
     * Autentica as requisições como o usuário informado, mockando o Firebase
     * para devolver claims correspondentes ao seu `firebase_uid` (SAD 4).
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

    private function userWithCredits(int $balance = self::INITIAL_BALANCE): User
    {
        return User::factory()->create([
            'firebase_uid' => 'uid-pipeline-owner',
            'plan_credits_balance' => $balance,
            'credits_used_this_month' => 0,
        ]);
    }

    /**
     * Etapa 1 do pipeline: a solicitação HTTP do usuário.
     *
     * @return array<string, mixed>
     */
    private function requestPipeline(User $user, Project $brand): array
    {
        return [
            'project_id' => $brand->id,
            'pipeline_type' => self::PIPELINE,
            'config' => [
                'language' => 'pt',
                'objective' => 'lançamento da linha de verão',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function n8nHeaders(string $secret = self::N8N_SECRET): array
    {
        return ['X-N8N-Secret' => $secret];
    }

    public function test_full_pipeline_flow(): void
    {
        Queue::fake();
        Event::fake([ExecutionUpdated::class, ExecutionFailed::class]);
        Http::fake([self::N8N_URL => Http::response(['received' => true, 'workflow' => 'briefing'])]);

        $user = $this->userWithCredits();
        $brand = Project::factory()->for($user)->create(['is_active' => true]);
        $headers = $this->actingAsFirebaseUser($user);

        /*
         * Etapas 1 a 6 (SAD 6.1): validação, Brand, assinatura, créditos,
         * criação da Execution e envio para a fila.
         */
        $response = $this->postJson(
            '/api/executions',
            $this->requestPipeline($user, $brand),
            $headers,
        );

        $response->assertAccepted()->assertJsonPath('status', 'queued');

        $executionId = $response->json('execution_id');

        // O Controller não pode ir além de `queued`: nem `processing`, nem
        // qualquer estado final pertencem a ele (SAD 6.8).
        $this->assertDatabaseHas('executions', [
            'id' => $executionId,
            'user_id' => $user->id,
            'project_id' => $brand->id,
            'pipeline_type' => self::PIPELINE,
            'status' => 'queued',
            'credits_consumed' => self::BRIEFING_COST,
            'result' => null,
            'completed_at' => null,
            'failed_at' => null,
        ]);

        // O débito acontece na criação, a partir de `plan_limits` (SAD 12.5).
        $user->refresh();
        $this->assertSame(self::INITIAL_BALANCE - self::BRIEFING_COST, $user->plan_credits_balance);
        $this->assertSame(self::BRIEFING_COST, $user->credits_used_this_month);

        Queue::assertPushedOn(
            'default',
            ProcessBriefingJob::class,
            fn (ProcessBriefingJob $job) => $job->executionId === $executionId,
        );

        // Nada foi enviado ao n8n ainda: a requisição HTTP do usuário jamais
        // espera pela IA (SAD 6.1).
        Http::assertNothingSent();
        Event::assertNothingDispatched();

        /*
         * Etapa 7: o Queue Worker executa o Job e chama o n8n.
         */
        (new ProcessBriefingJob($executionId))->handle(app(N8nService::class));

        $execution = $user->executions()->findOrFail($executionId);

        $this->assertSame('processing', $execution->status);
        $this->assertNull($execution->result);

        Http::assertSent(function (Request $request) use ($executionId, $brand): bool {
            $payload = $request->data();

            $this->assertSame(self::N8N_URL, $request->url());
            $this->assertSame(self::N8N_SECRET, $request->header('X-N8N-Secret')[0]);
            $this->assertSame($executionId, $payload['execution_id']);
            $this->assertSame(self::PIPELINE, $payload['pipeline_type']);
            $this->assertSame($brand->id, $payload['project_id']);
            // O n8n precisa saber para onde devolver o resultado (SAD 7.3).
            $this->assertSame(self::APP_URL.self::CALLBACK_ENDPOINT, $payload['webhook_callback']);

            return true;
        });

        Event::assertDispatched(
            ExecutionUpdated::class,
            fn (ExecutionUpdated $event) => $event->execution->id === $executionId
                && $event->execution->status === 'processing',
        );

        /*
         * Etapas 8 a 11: o n8n processa e devolve o resultado pelo callback
         * autenticado, único caminho autorizado a encerrar uma Execution
         * (SAD 7.7).
         */
        $result = [
            'diagnosis' => ['posicionamento' => 'premium', 'tom' => 'consultivo'],
            'calendar' => [
                ['day' => 1, 'theme' => 'teaser da coleção'],
                ['day' => 2, 'theme' => 'prova social'],
            ],
        ];

        $this->postJson(self::CALLBACK_ENDPOINT, [
            'execution_id' => $executionId,
            'status' => 'completed',
            'pipeline_type' => self::PIPELINE,
            'tokens_used' => 18432,
            'credits_consumed' => self::BRIEFING_COST,
            'result' => $result,
            'finished_at' => '2026-07-30T12:00:00Z',
            'error' => null,
        ], $this->n8nHeaders())
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('ignored', false);

        $execution->refresh();

        $this->assertSame('completed', $execution->status);
        $this->assertSame($result, $execution->result);
        $this->assertSame(18432, $execution->tokens_used);
        $this->assertSame('2026-07-30 12:00:00', $execution->completed_at->utc()->toDateTimeString());
        $this->assertNull($execution->error_message);
        $this->assertNull($execution->failed_at);

        // Uma Execution, dois broadcasts de mudança de estado: `processing` e
        // `completed`. Nenhuma falha em nenhum momento do trajeto.
        Event::assertDispatchedTimes(ExecutionUpdated::class, 2);
        Event::assertNotDispatched(ExecutionFailed::class);

        // O callback não mexe em contabilidade: o débito continua sendo o único,
        // feito na criação a partir de `plan_limits` (CLAUDE.md regra 9).
        $user->refresh();
        $this->assertSame(self::INITIAL_BALANCE - self::BRIEFING_COST, $user->plan_credits_balance);
        $this->assertSame(self::BRIEFING_COST, $user->credits_used_this_month);
        $this->assertSame(self::BRIEFING_COST, $execution->credits_consumed);

        /*
         * Fecho do ciclo: o resultado só é legível pelo dono, e apenas no
         * endpoint de detalhe (SAD 5.12 e CLAUDE.md regra 11).
         */
        $this->getJson("/api/executions/{$executionId}", $headers)
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('result.diagnosis.posicionamento', 'premium');

        $listing = $this->getJson("/api/executions?project_id={$brand->id}", $headers)->assertOk();

        $this->assertSame(1, $listing->json('total'));
        $this->assertArrayNotHasKey('result', $listing->json('data.0'));
    }

    public function test_pipeline_handles_n8n_failure(): void
    {
        Queue::fake();
        Event::fake([ExecutionUpdated::class, ExecutionFailed::class]);
        // Droplet 2 inacessível: a VPC recusa a conexão antes de qualquer
        // resposta HTTP (SAD 12.2).
        Http::fake([self::N8N_URL => Http::failedConnection('Connection refused')]);

        $user = $this->userWithCredits();
        $brand = Project::factory()->for($user)->create(['is_active' => true]);
        $headers = $this->actingAsFirebaseUser($user);

        $executionId = $this->postJson(
            '/api/executions',
            $this->requestPipeline($user, $brand),
            $headers,
        )->assertAccepted()->json('execution_id');

        $job = new ProcessBriefingJob($executionId);

        // Toda tentativa do Worker falha, e a exceção sobe: quem decide entre
        // novo retry e desistência é o Worker, não o Job (SAD 8.3).
        foreach (range(1, $job->tries) as $attempt) {
            try {
                $job->handle(app(N8nService::class));
                $this->fail("A tentativa {$attempt} deveria ter falhado.");
            } catch (N8nConnectionException $e) {
                // A exceção nunca pode carregar o endereço nem o segredo do
                // Droplet 2 até a camada de log (SAD 7.6).
                $this->assertStringNotContainsString(self::N8N_URL, $e->getMessage());
                $this->assertStringNotContainsString(self::N8N_SECRET, $e->getMessage());
            }

            // Enquanto há retry pendente a Execution permanece `processing`:
            // um estado final aqui mentiria ao usuário sobre um pipeline que
            // ainda pode ser despachado.
            $this->assertSame('processing', $user->executions()->find($executionId)->status);
        }

        // Tentativas esgotadas: o Worker chama failed() e só então o estado
        // terminal é gravado.
        $job->failed(N8nConnectionException::unreachable($executionId, 'ConnectionException'));

        $execution = $user->executions()->findOrFail($executionId);

        $this->assertSame('failed', $execution->status);
        $this->assertNotNull($execution->failed_at);
        $this->assertNull($execution->completed_at);
        // `result` só existe após conclusão bem-sucedida (CLAUDE.md §6).
        $this->assertNull($execution->result);

        // O diagnóstico que chega ao usuário é genérico; o técnico fica no log.
        $this->assertNotEmpty($execution->error_message);
        $this->assertStringNotContainsString(self::N8N_URL, $execution->error_message);
        $this->assertStringNotContainsString(self::N8N_SECRET, $execution->error_message);

        Event::assertDispatched(
            ExecutionFailed::class,
            fn (ExecutionFailed $event) => $event->execution->id === $executionId
                && $event->execution->status === 'failed',
        );

        // Cada tentativa reanuncia `processing` antes de tentar o despacho. O
        // broadcast carrega apenas metadados de estado (SAD 9.6), então repetir
        // o mesmo estado é inofensivo para a interface — mas é comportamento
        // observável e fica registrado aqui.
        Event::assertDispatchedTimes(ExecutionUpdated::class, $job->tries);

        // Estado atual e deliberado do domínio: o crédito debitado na criação
        // não é estornado quando o pipeline falha. A política de estorno é uma
        // lacuna registrada em SAD 6.2 como [A DEFINIR] — este assert existe
        // para que a decisão, quando vier, quebre o teste em vez de passar
        // despercebida.
        $user->refresh();
        $this->assertSame(self::INITIAL_BALANCE - self::BRIEFING_COST, $user->plan_credits_balance);

        // Depois de falhar, a Execution é imutável para o n8n: um callback
        // atrasado não a ressuscita (SAD 7.8).
        $this->postJson(self::CALLBACK_ENDPOINT, [
            'execution_id' => $executionId,
            'status' => 'completed',
            'result' => ['diagnosis' => 'resultado atrasado'],
        ], $this->n8nHeaders())
            ->assertOk()
            ->assertJsonPath('ignored', true);

        $this->assertSame('failed', $execution->refresh()->status);
        $this->assertNull($execution->result);
    }

    public function test_pipeline_blocks_when_no_credits(): void
    {
        Queue::fake();
        Event::fake([ExecutionUpdated::class, ExecutionFailed::class]);
        Http::fake();

        $user = $this->userWithCredits(self::BRIEFING_COST - 1);
        $brand = Project::factory()->for($user)->create(['is_active' => true]);

        $this->postJson(
            '/api/executions',
            $this->requestPipeline($user, $brand),
            $this->actingAsFirebaseUser($user),
        )
            ->assertStatus(402)
            ->assertJsonPath('error', 'insufficient_credits')
            ->assertJsonPath('required', self::BRIEFING_COST)
            ->assertJsonPath('available', self::BRIEFING_COST - 1)
            ->assertJsonPath('cta', 'Buy AI Credits');

        // SAD 12.7: saldo insuficiente não deixa rastro algum — nem Execution,
        // nem fila, nem débito, nem chamada ao n8n.
        $this->assertDatabaseCount('executions', 0);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
        Event::assertNothingDispatched();

        $this->assertSame(self::BRIEFING_COST - 1, $user->refresh()->plan_credits_balance);
        $this->assertSame(0, $user->credits_used_this_month);
    }
}
