<?php

namespace Tests\Unit\Services;

use App\Exceptions\FeatureNotAvailableException;
use App\Exceptions\InsufficientCreditsException;
use App\Models\PlanLimit;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * O Service é a autoridade sobre o saldo (SAD 12).
 *
 * Os testes tocam o banco de propósito: a garantia que interessa aqui — o saldo
 * lido da fonte da verdade dentro da transação de débito (SAD N.5) — não pode
 * ser demonstrada com models em memória.
 */
class CreditServiceTest extends TestCase
{
    use RefreshDatabase;

    private const ACTION = 'briefing_generation';

    private const COST = 5;

    /**
     * Plano do usuário criado pela factory — o mesmo que o gating consulta.
     */
    private const PLAN = 'free';

    protected function setUp(): void
    {
        parent::setUp();

        $this->definirCusto(self::ACTION, self::COST);
    }

    private function service(): CreditService
    {
        return app(CreditService::class);
    }

    /**
     * Custo registrado em plan_limits — a única fonte oficial (SAD 12.9).
     */
    private function definirCusto(string $action, int $credits, bool $habilitado = true): void
    {
        PlanLimit::query()->create([
            'plan' => self::PLAN,
            'action' => $action,
            'credits' => $credits,
            'is_enabled' => $habilitado,
        ]);
    }

    public function test_can_execute_returns_false_when_insufficient(): void
    {
        $user = User::factory()->create(['plan_credits_balance' => self::COST - 1]);

        $this->assertFalse($this->service()->canExecute($user, self::ACTION));
    }

    public function test_can_execute_returns_true_when_the_balance_covers_the_cost(): void
    {
        $user = User::factory()->create(['plan_credits_balance' => self::COST]);

        $this->assertTrue($this->service()->canExecute($user, self::ACTION));
    }

    /**
     * A verificação considera a soma dos dois saldos (SAD 12.10), ainda que o
     * débito respeite a ordem de consumo.
     */
    public function test_can_execute_considers_both_balances_together(): void
    {
        $user = User::factory()->create([
            'plan_credits_balance' => 2,
            'purchased_credits_balance' => 3,
        ]);

        $this->assertTrue($this->service()->canExecute($user, self::ACTION));

        $user->purchased_credits_balance = 2;

        $this->assertFalse($this->service()->canExecute($user, self::ACTION));
    }

    public function test_deduct_decrements_balance_in_transaction(): void
    {
        $user = User::factory()->create(['plan_credits_balance' => 100]);

        // O débito precisa ocorrer em um nível de transação mais profundo que o
        // do chamador: é isso que garante que leitura do saldo e escrita sejam
        // atômicas diante de execuções concorrentes (SAD N.5).
        $nivelDoChamador = DB::transactionLevel();
        $niveisDaEscrita = [];
        $commits = 0;

        DB::listen(function (QueryExecuted $query) use (&$niveisDaEscrita): void {
            if (str_contains($query->sql, 'update "users"')) {
                $niveisDaEscrita[] = DB::transactionLevel();
            }
        });

        Event::listen(TransactionCommitted::class, function () use (&$commits): void {
            $commits++;
        });

        $this->service()->deduct($user, self::ACTION);

        $this->assertNotEmpty($niveisDaEscrita, 'Nenhuma escrita em users foi observada.');
        $this->assertGreaterThan($nivelDoChamador, min($niveisDaEscrita));
        $this->assertGreaterThan(0, $commits);
        $this->assertSame($nivelDoChamador, DB::transactionLevel());

        // Persistido no banco e refletido na instância recebida pelo chamador.
        $this->assertSame(100 - self::COST, (int) $user->plan_credits_balance);
        $this->assertSame(self::COST, (int) $user->credits_used_this_month);
        $this->assertFalse($user->isDirty([
            'plan_credits_balance',
            'purchased_credits_balance',
            'credits_used_this_month',
        ]));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'plan_credits_balance' => 100 - self::COST,
            'purchased_credits_balance' => 0,
            'credits_used_this_month' => self::COST,
        ]);
    }

    /**
     * Ordem de consumo da Seção 12.3: o crédito que expira é gasto antes do
     * crédito que não expira, e um único débito pode atravessar os dois saldos.
     */
    public function test_deducts_from_plan_credits_before_purchased_credits(): void
    {
        $user = User::factory()->create([
            'plan_credits_balance' => 30,
            'purchased_credits_balance' => 200,
        ]);

        $this->definirCusto('brand_research', 50);

        $this->service()->deduct($user, 'brand_research');

        // O exemplo literal da Seção 12.3: 30 + 200, débito de 50 → 0 + 180.
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'plan_credits_balance' => 0,
            'purchased_credits_balance' => 180,
            'credits_used_this_month' => 50,
        ]);

        // Enquanto o saldo do plano cobrir o custo, o comprado não é tocado.
        $this->service()->deduct($user, self::ACTION);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'plan_credits_balance' => 0,
            'purchased_credits_balance' => 180 - self::COST,
        ]);

        $comSaldoDePlano = User::factory()->create([
            'plan_credits_balance' => 40,
            'purchased_credits_balance' => 10,
        ]);

        $this->service()->deduct($comSaldoDePlano, self::ACTION);

        $this->assertDatabaseHas('users', [
            'id' => $comSaldoDePlano->id,
            'plan_credits_balance' => 40 - self::COST,
            'purchased_credits_balance' => 10,
        ]);
    }

    /**
     * O gating precede o saldo e tem desfecho próprio: 403, não 402 (SAD 12.5).
     */
    public function test_blocks_when_feature_not_enabled_for_plan(): void
    {
        $this->definirCusto('image_generation', 7, habilitado: false);

        // Saldo de sobra: o bloqueio é do plano, não do bolso.
        $user = User::factory()->create([
            'plan_credits_balance' => 1000,
            'purchased_credits_balance' => 1000,
        ]);

        try {
            $this->service()->canExecute($user, 'image_generation');
            $this->fail('FeatureNotAvailableException was not thrown.');
        } catch (FeatureNotAvailableException $e) {
            $this->assertSame($user->id, $e->userId);
            $this->assertSame(self::PLAN, $e->plan);
            $this->assertSame('image_generation', $e->action);
        }

        // Uma ação habilitada para o mesmo plano segue passando.
        $this->assertTrue($this->service()->canExecute($user, self::ACTION));
    }

    /**
     * Ausência de linha `(plan, action)` não é bloqueio: a coluna nasce
     * habilitada e nenhuma configuração de gating existe ainda (SAD 12.15).
     */
    public function test_missing_plan_limit_row_does_not_gate_the_action(): void
    {
        $user = User::factory()->create(['plan_credits_balance' => 100]);

        $this->definirCusto('seo_optimization', 10);

        // A linha gravada é do plano `free`; o usuário pro não tem linha alguma.
        $usuarioPro = User::factory()->pro()->create();

        $this->assertTrue($this->service()->canExecute($usuarioPro, 'seo_optimization'));
        $this->assertTrue($this->service()->canExecute($user, 'seo_optimization'));
    }

    public function test_deduct_accumulates_the_monthly_consumption(): void
    {
        $user = User::factory()->create([
            'plan_credits_balance' => 100,
            'credits_used_this_month' => 12,
        ]);

        $this->service()->deduct($user, self::ACTION);
        $this->service()->deduct($user, self::ACTION);

        // Saldo e consumo mensal descrevem o mesmo débito por ângulos opostos.
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'plan_credits_balance' => 100 - (2 * self::COST),
            'credits_used_this_month' => 12 + (2 * self::COST),
        ]);
    }

    public function test_deduct_reads_the_balance_from_the_source_of_truth(): void
    {
        $user = User::factory()->create(['plan_credits_balance' => 100]);

        // Simula uma execução concorrente que já consumiu o saldo entre a
        // verificação e o débito. A instância em memória segue defasada.
        DB::table('users')->where('id', $user->id)->update(['plan_credits_balance' => 10]);

        $this->service()->deduct($user, self::ACTION);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'plan_credits_balance' => 10 - self::COST,
        ]);
    }

    public function test_deduct_rejects_a_stale_balance_that_no_longer_covers_the_cost(): void
    {
        $user = User::factory()->create(['plan_credits_balance' => 100]);

        DB::table('users')->where('id', $user->id)->update([
            'plan_credits_balance' => self::COST - 1,
        ]);

        try {
            $this->service()->deduct($user, self::ACTION);
            $this->fail('InsufficientCreditsException was not thrown.');
        } catch (InsufficientCreditsException $e) {
            $this->assertSame($user->id, $e->userId);
            $this->assertSame(self::ACTION, $e->action);
            $this->assertSame(self::COST, $e->required);
            $this->assertSame(self::COST - 1, $e->available);
        }

        // Nada foi debitado: nenhum dos dois saldos se move quando a soma não
        // cobre o custo (SAD 12.12).
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'plan_credits_balance' => self::COST - 1,
            'purchased_credits_balance' => 0,
        ]);
    }

    /**
     * A soma insuficiente não pode debitar parcialmente o saldo do plano antes
     * de descobrir que o comprado não cobre o resto (SAD 12.12).
     */
    public function test_deduct_leaves_both_balances_untouched_when_the_sum_is_insufficient(): void
    {
        $this->definirCusto('brand_research', 50);

        $user = User::factory()->create([
            'plan_credits_balance' => 30,
            'purchased_credits_balance' => 10,
        ]);

        try {
            $this->service()->deduct($user, 'brand_research');
            $this->fail('InsufficientCreditsException was not thrown.');
        } catch (InsufficientCreditsException $e) {
            $this->assertSame(50, $e->required);
            $this->assertSame(40, $e->available);
        }

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'plan_credits_balance' => 30,
            'purchased_credits_balance' => 10,
            'credits_used_this_month' => 0,
        ]);
    }

    public function test_deduct_uses_the_cost_registered_in_plan_limits(): void
    {
        $this->definirCusto('brand_research', 50);

        $user = User::factory()->create(['plan_credits_balance' => 60]);

        $this->service()->deduct($user, 'brand_research');

        $this->assertSame(10, (int) $user->plan_credits_balance);
    }

    public function test_cost_is_cached_under_the_official_plan_limits_key(): void
    {
        $this->service()->canExecute(User::factory()->create(), self::ACTION);

        $this->assertSame(self::COST, Cache::get('plan_limits:'.self::ACTION));
    }

    /**
     * Usuários Enterprise (limite de créditos = null) nunca devem ser bloqueados.
     * Este teste verifica o comportamento usando uma subclasse anônima para
     * sobrescrever o método cost(), simulando um plano ilimitado.
     * Essa abordagem testa o comportamento real do serviço sem depender de mocks
     * estáticos do PlanLimit (que já foi carregado na memória pelo Laravel e
     * resiste ao Mockery::alias).
     */
    public function test_enterprise_user_can_always_execute(): void
    {
        $service = $this->serviceSemTeto();

        $user = User::factory()->enterprise()->create(['plan_credits_balance' => 0]);

        $this->assertTrue($service->canExecute($user, 'brand_research'));

        $service->deduct($user, 'brand_research');

        // Sem teto não há débito: saldo e consumo mensal permanecem intactos.
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'plan_credits_balance' => 0,
            'purchased_credits_balance' => 0,
            'credits_used_this_month' => 0,
        ]);
    }

    /**
     * O modelo de dois saldos não reintroduz teto para quem não tem: nem o
     * saldo comprado é consumido, nem o saldo do plano zerado bloqueia.
     */
    public function test_enterprise_still_unlimited_with_dual_balance(): void
    {
        $service = $this->serviceSemTeto();

        $user = User::factory()->enterprise()->create([
            'plan_credits_balance' => 0,
            'purchased_credits_balance' => 25,
        ]);

        $this->assertTrue($service->canExecute($user, 'brand_research'));

        $service->deduct($user, 'brand_research');

        // O saldo comprado é do usuário e não financia um plano sem teto.
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'plan_credits_balance' => 0,
            'purchased_credits_balance' => 25,
            'credits_used_this_month' => 0,
        ]);

        $this->assertSame(
            ['plan' => 0, 'purchased' => 25, 'total' => 25],
            $service->getRemainingBalance($user->refresh()),
        );
    }

    public function test_get_remaining_balance_returns_both_balances_and_the_sum(): void
    {
        $user = User::factory()->create([
            'plan_credits_balance' => 42,
            'purchased_credits_balance' => 8,
        ]);

        $this->assertSame(
            ['plan' => 42, 'purchased' => 8, 'total' => 50],
            $this->service()->getRemainingBalance($user),
        );
    }

    /**
     * Plano sem teto de créditos: `plan_limits.credits` é NULL no PostgreSQL,
     * valor que a suíte não consegue gravar no SQLite (SAD 12.15).
     */
    private function serviceSemTeto(): CreditService
    {
        return new class extends CreditService
        {
            protected function cost(string $action): ?int
            {
                return null;
            }
        };
    }
}
