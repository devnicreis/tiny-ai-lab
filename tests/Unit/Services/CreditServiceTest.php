<?php

namespace Tests\Unit\Services;

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
    private function definirCusto(string $action, int $credits): void
    {
        PlanLimit::query()->create([
            'plan' => 'free',
            'action' => $action,
            'credits' => $credits,
        ]);
    }

    public function test_can_execute_returns_false_when_insufficient(): void
    {
        $user = User::factory()->create(['credits_balance' => self::COST - 1]);

        $this->assertFalse($this->service()->canExecute($user, self::ACTION));
    }

    public function test_can_execute_returns_true_when_the_balance_covers_the_cost(): void
    {
        $user = User::factory()->create(['credits_balance' => self::COST]);

        $this->assertTrue($this->service()->canExecute($user, self::ACTION));
    }

    public function test_deduct_decrements_balance_in_transaction(): void
    {
        $user = User::factory()->create(['credits_balance' => 100]);

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
        $this->assertSame(100 - self::COST, (int) $user->credits_balance);
        $this->assertSame(self::COST, (int) $user->credits_used_this_month);
        $this->assertFalse($user->isDirty(['credits_balance', 'credits_used_this_month']));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'credits_balance' => 100 - self::COST,
            'credits_used_this_month' => self::COST,
        ]);
    }

    public function test_deduct_accumulates_the_monthly_consumption(): void
    {
        $user = User::factory()->create([
            'credits_balance' => 100,
            'credits_used_this_month' => 12,
        ]);

        $this->service()->deduct($user, self::ACTION);
        $this->service()->deduct($user, self::ACTION);

        // Saldo e consumo mensal descrevem o mesmo débito por ângulos opostos.
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'credits_balance' => 100 - (2 * self::COST),
            'credits_used_this_month' => 12 + (2 * self::COST),
        ]);
    }

    public function test_deduct_reads_the_balance_from_the_source_of_truth(): void
    {
        $user = User::factory()->create(['credits_balance' => 100]);

        // Simula uma execução concorrente que já consumiu o saldo entre a
        // verificação e o débito. A instância em memória segue defasada.
        DB::table('users')->where('id', $user->id)->update(['credits_balance' => 10]);

        $this->service()->deduct($user, self::ACTION);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'credits_balance' => 10 - self::COST,
        ]);
    }

    public function test_deduct_rejects_a_stale_balance_that_no_longer_covers_the_cost(): void
    {
        $user = User::factory()->create(['credits_balance' => 100]);

        DB::table('users')->where('id', $user->id)->update(['credits_balance' => self::COST - 1]);

        try {
            $this->service()->deduct($user, self::ACTION);
            $this->fail('InsufficientCreditsException was not thrown.');
        } catch (InsufficientCreditsException $e) {
            $this->assertSame($user->id, $e->userId);
            $this->assertSame(self::ACTION, $e->action);
            $this->assertSame(self::COST, $e->required);
            $this->assertSame(self::COST - 1, $e->available);
        }

        // Nada foi debitado: o saldo do banco permanece intacto.
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'credits_balance' => self::COST - 1,
        ]);
    }

    public function test_deduct_uses_the_cost_registered_in_plan_limits(): void
    {
        $this->definirCusto('brand_research', 50);

        $user = User::factory()->create(['credits_balance' => 60]);

        $this->service()->deduct($user, 'brand_research');

        $this->assertSame(10, (int) $user->credits_balance);
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
        $service = new class extends CreditService
        {
            protected function cost(string $action): ?int
            {
                return null;
            }
        };

        $user = User::factory()->enterprise()->create(['credits_balance' => 0]);

        $this->assertTrue($service->canExecute($user, 'brand_research'));

        $service->deduct($user, 'brand_research');

        // Sem teto não há débito: saldo e consumo mensal permanecem intactos.
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'credits_balance' => 0,
            'credits_used_this_month' => 0,
        ]);
    }

    public function test_get_remaining_balance_returns_the_current_balance(): void
    {
        $user = User::factory()->create(['credits_balance' => 42]);

        $this->assertSame(42, $this->service()->getRemainingBalance($user));
    }
}
