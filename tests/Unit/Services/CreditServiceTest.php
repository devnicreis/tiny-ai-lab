<?php

namespace Tests\Unit\Services;

use App\Enums\OrganizationRole;
use App\Exceptions\FeatureNotAvailableException;
use App\Exceptions\InsufficientCreditsException;
use App\Exceptions\NoActivePlanException;
use App\Exceptions\PersonalCreditsConfirmationRequiredException;
use App\Models\Organization;
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
 * O Service é a autoridade sobre plano e saldo (SAD 12).
 *
 * A entidade cobrada é a Organização; o usuário entra apenas pela carteira
 * pessoal. Os testes tocam o banco de propósito: as garantias que interessam
 * aqui — a ordem de consumo entre três carteiras e o saldo lido da fonte da
 * verdade dentro da transação de débito (SAD N.5) — não podem ser demonstradas
 * com models em memória.
 */
class CreditServiceTest extends TestCase
{
    use RefreshDatabase;

    private const ACTION = 'briefing_generation';

    private const COST = 5;

    /**
     * Plano comercial da Seção 12.6, gravado em `organizations.plan`.
     */
    private const PLAN = 'copy_briefing';

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
     * Custo registrado em plan_limits — a única fonte oficial (SAD 12.14).
     */
    private function definirCusto(string $action, int $credits, bool $habilitado = true, string $plan = self::PLAN): void
    {
        PlanLimit::query()->create([
            'plan' => $plan,
            'action' => $action,
            'credits' => $credits,
            'is_enabled' => $habilitado,
        ]);
    }

    /**
     * Organização com plano e carteiras próprias (SAD 12.2).
     */
    private function organizacao(int $planCredits = 0, int $purchasedCredits = 0, ?string $plan = self::PLAN): Organization
    {
        return Organization::factory()->withOwner()->create([
            'plan' => $plan,
            'plan_credits_balance' => $planCredits,
            'purchased_credits_balance' => $purchasedCredits,
        ]);
    }

    /**
     * Membro da organização, com sua carteira pessoal (SAD 12.2).
     */
    private function membro(Organization $organization, int $carteiraPessoal = 0, OrganizationRole $role = OrganizationRole::Admin): User
    {
        $user = User::factory()->create(['purchased_credits_balance' => $carteiraPessoal]);

        $organization->users()->attach($user->id, [
            'role' => $role->value,
            'joined_at' => now(),
        ]);

        return $user;
    }

    // ---------------------------------------------------------------------
    // Ordem de consumo (SAD 12.3)
    // ---------------------------------------------------------------------

    /**
     * O crédito que expira é gasto antes de qualquer outro: enquanto a carteira
     * do plano cobrir o custo, nenhuma outra é tocada.
     */
    public function test_deducts_org_plan_credits_first(): void
    {
        $organization = $this->organizacao(planCredits: 40, purchasedCredits: 10);
        $user = $this->membro($organization, carteiraPessoal: 200);

        $this->service()->deduct($organization, $user, self::ACTION);

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'plan_credits_balance' => 40 - self::COST,
            'purchased_credits_balance' => 10,
            'plan_credits_used_this_month' => self::COST,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'purchased_credits_balance' => 200,
        ]);
    }

    /**
     * Esgotada a carteira do plano, o débito segue para o crédito comprado pela
     * organização — nunca direto para o bolso do usuário.
     */
    public function test_falls_back_to_org_purchased_when_plan_exhausted(): void
    {
        $organization = $this->organizacao(planCredits: 0, purchasedCredits: 30);
        $user = $this->membro($organization, carteiraPessoal: 200);

        $this->service()->deduct($organization, $user, self::ACTION);

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'plan_credits_balance' => 0,
            'purchased_credits_balance' => 30 - self::COST,
            // Nada saiu da carteira do plano: o consumo do mês não se move.
            'plan_credits_used_this_month' => 0,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'purchased_credits_balance' => 200,
        ]);
    }

    /**
     * A carteira pessoal é o último recurso, e só com confirmação explícita.
     */
    public function test_falls_back_to_user_personal_as_last_resort(): void
    {
        $organization = $this->organizacao(planCredits: 0, purchasedCredits: 0);
        $user = $this->membro($organization, carteiraPessoal: 200);

        $this->service()->deduct($organization, $user, self::ACTION, confirmPersonalCredits: true);

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'plan_credits_balance' => 0,
            'purchased_credits_balance' => 0,
            'plan_credits_used_this_month' => 0,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'purchased_credits_balance' => 200 - self::COST,
        ]);
    }

    /**
     * O exemplo literal da Seção 12.3: 30 + 10 + 200, débito de 50 → 0 + 0 + 190.
     */
    public function test_partial_deduction_spans_multiple_wallets(): void
    {
        $this->definirCusto('brand_research', 50);

        $organization = $this->organizacao(planCredits: 30, purchasedCredits: 10);
        $user = $this->membro($organization, carteiraPessoal: 200);

        $this->service()->deduct($organization, $user, 'brand_research', confirmPersonalCredits: true);

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'plan_credits_balance' => 0,
            'purchased_credits_balance' => 0,
            // Apenas a parcela que saiu da carteira 1 é consumo de ciclo.
            'plan_credits_used_this_month' => 30,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'purchased_credits_balance' => 190,
        ]);

        // As instâncias do chamador refletem o que foi persistido, sem ficarem
        // sujas.
        $this->assertSame(0, (int) $organization->plan_credits_balance);
        $this->assertSame(190, (int) $user->purchased_credits_balance);
        $this->assertFalse($organization->isDirty());
        $this->assertFalse($user->isDirty());
    }

    // ---------------------------------------------------------------------
    // Eixo 1 — plano ativo (SAD 12.18)
    // ---------------------------------------------------------------------

    /**
     * A regra de segurança da Seção 12.18: sem plano, o bloqueio vem antes do
     * feature gating.
     *
     * Com `plan_limits` vazio para a ação, o gating responderia "habilitado" —
     * é a convenção de linha ausente. Se a ordem fosse invertida, a ausência de
     * contrato viraria acesso irrestrito. O contraprova está no fim do teste: a
     * mesma ação, na mesma tabela vazia, passa quando existe plano.
     */
    public function test_organization_without_plan_is_blocked_before_feature_gating(): void
    {
        $semPlano = $this->organizacao(planCredits: 1000, purchasedCredits: 1000, plan: null);
        $user = $this->membro($semPlano, carteiraPessoal: 1000);

        $this->assertSame(
            0,
            PlanLimit::query()->where('action', 'create_post')->count(),
            'O cenário exige uma ação sem nenhuma linha em plan_limits.',
        );

        try {
            $this->service()->canExecute($semPlano, $user, 'create_post');
            $this->fail('NoActivePlanException was not thrown.');
        } catch (NoActivePlanException $e) {
            $this->assertSame($semPlano->id, $e->organizationId);
            $this->assertSame('create_post', $e->action);
        }

        // Saldo de sobra em todas as carteiras não contorna a ausência de plano,
        // nem a carteira pessoal do usuário.
        $this->expectException(NoActivePlanException::class);
        $this->service()->canExecute($semPlano, $user, 'create_post', confirmPersonalCredits: true);
    }

    public function test_a_plan_makes_the_same_ungated_action_pass(): void
    {
        $comPlano = $this->organizacao(planCredits: 1000);
        $user = $this->membro($comPlano);

        // Mesma ação sem linha em plan_limits do teste anterior: com plano, a
        // convenção de linha ausente libera.
        $this->assertTrue($this->service()->canExecute($comPlano, $user, 'create_post'));
    }

    public function test_deduct_is_not_the_place_that_checks_the_plan(): void
    {
        // `deduct()` é a etapa definitiva de saldo, não de contrato: quem barra
        // uma organização sem plano é `canExecute()`, antes de qualquer débito
        // (SAD 12.10). O teste registra a fronteira para que ninguém a mova sem
        // perceber.
        $semPlano = $this->organizacao(planCredits: 100, plan: null);
        $user = $this->membro($semPlano);

        $this->service()->deduct($semPlano, $user, self::ACTION);

        $this->assertDatabaseHas('organizations', [
            'id' => $semPlano->id,
            'plan_credits_balance' => 100 - self::COST,
        ]);
    }

    // ---------------------------------------------------------------------
    // Eixo 2 — feature gating (SAD 12.5)
    // ---------------------------------------------------------------------

    /**
     * O gating é do plano da organização, nunca do usuário: o mesmo usuário tem
     * acesso a `image_generation` em uma organização e não tem em outra.
     */
    public function test_feature_gating_uses_organization_plan_not_user(): void
    {
        $this->definirCusto('image_generation', 7, habilitado: false, plan: 'copy_briefing');
        $this->definirCusto('image_generation', 7, habilitado: true, plan: 'full_pass');

        $semAcesso = $this->organizacao(planCredits: 1000, plan: 'copy_briefing');
        $comAcesso = $this->organizacao(planCredits: 1000, plan: 'full_pass');

        $user = $this->membro($semAcesso, carteiraPessoal: 1000);
        $comAcesso->users()->attach($user->id, [
            'role' => OrganizationRole::Admin->value,
            'joined_at' => now(),
        ]);

        // Saldo de sobra nas duas: o que difere é exclusivamente o plano da org.
        try {
            $this->service()->canExecute($semAcesso, $user, 'image_generation');
            $this->fail('FeatureNotAvailableException was not thrown.');
        } catch (FeatureNotAvailableException $e) {
            $this->assertSame($semAcesso->id, $e->organizationId);
            $this->assertSame('copy_briefing', $e->plan);
            $this->assertSame('image_generation', $e->action);
            $this->assertSame($user->id, $e->userId);
        }

        $this->assertTrue($this->service()->canExecute($comAcesso, $user, 'image_generation'));
    }

    /**
     * Ausência de linha `(plan, action)` não é bloqueio: a coluna nasce
     * habilitada e nenhuma configuração de gating existe ainda (SAD 12.15).
     */
    public function test_missing_plan_limit_row_does_not_gate_the_action(): void
    {
        $this->definirCusto('seo_optimization', 10, plan: 'copy_briefing');

        $comLinha = $this->organizacao(planCredits: 100, plan: 'copy_briefing');
        $semLinha = $this->organizacao(planCredits: 100, plan: 'studio_visual');

        $this->assertTrue($this->service()->canExecute($comLinha, $this->membro($comLinha), 'seo_optimization'));
        $this->assertTrue($this->service()->canExecute($semLinha, $this->membro($semLinha), 'seo_optimization'));
    }

    // ---------------------------------------------------------------------
    // Eixo 3 — saldo (SAD 12.10 e 12.12)
    // ---------------------------------------------------------------------

    public function test_can_execute_returns_true_when_the_balance_covers_the_cost(): void
    {
        $organization = $this->organizacao(planCredits: self::COST);

        $this->assertTrue($this->service()->canExecute($organization, $this->membro($organization), self::ACTION));
    }

    /**
     * A verificação considera a soma das três carteiras (SAD 12.10), ainda que
     * o débito respeite a ordem de consumo.
     */
    public function test_can_execute_considers_the_three_wallets_together(): void
    {
        $organization = $this->organizacao(planCredits: 2, purchasedCredits: 2);
        $user = $this->membro($organization, carteiraPessoal: 1);

        $this->assertTrue(
            $this->service()->canExecute($organization, $user, self::ACTION, confirmPersonalCredits: true),
        );

        $user->purchased_credits_balance = 0;

        $this->expectException(InsufficientCreditsException::class);
        $this->service()->canExecute($organization, $user, self::ACTION, confirmPersonalCredits: true);
    }

    /**
     * Saldo insuficiente vence a pergunta da carteira pessoal: se nem as três
     * carteiras cobrem o custo, confirmar não destravaria nada (SAD 12.19).
     */
    public function test_insufficient_balance_is_reported_before_asking_for_confirmation(): void
    {
        $this->definirCusto('brand_research', 50);

        $organization = $this->organizacao(planCredits: 30, purchasedCredits: 10);
        $user = $this->membro($organization, carteiraPessoal: 5);

        try {
            $this->service()->canExecute($organization, $user, 'brand_research');
            $this->fail('InsufficientCreditsException was not thrown.');
        } catch (InsufficientCreditsException $e) {
            $this->assertSame(50, $e->required);
            $this->assertSame(45, $e->available);
            $this->assertSame($organization->id, $e->organizationId);
            $this->assertSame($user->id, $e->userId);
        }
    }

    /**
     * A soma insuficiente não pode debitar parcialmente uma carteira antes de
     * descobrir que as demais não cobrem o resto (SAD 12.12).
     */
    public function test_deduct_leaves_every_wallet_untouched_when_the_sum_is_insufficient(): void
    {
        $this->definirCusto('brand_research', 50);

        $organization = $this->organizacao(planCredits: 30, purchasedCredits: 10);
        $user = $this->membro($organization, carteiraPessoal: 5);

        try {
            $this->service()->deduct($organization, $user, 'brand_research', confirmPersonalCredits: true);
            $this->fail('InsufficientCreditsException was not thrown.');
        } catch (InsufficientCreditsException $e) {
            $this->assertSame(50, $e->required);
            $this->assertSame(45, $e->available);
        }

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'plan_credits_balance' => 30,
            'purchased_credits_balance' => 10,
            'plan_credits_used_this_month' => 0,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'purchased_credits_balance' => 5,
        ]);
    }

    // ---------------------------------------------------------------------
    // Eixo 4 — confirmação da carteira pessoal (SAD 12.19)
    // ---------------------------------------------------------------------

    public function test_preview_flags_when_personal_wallet_needed(): void
    {
        $this->definirCusto('brand_research', 50);

        $organization = $this->organizacao(planCredits: 30, purchasedCredits: 10);
        $user = $this->membro($organization, carteiraPessoal: 200);

        $this->assertSame(
            [
                'cost' => 50,
                'org_plan' => 30,
                'org_purchased' => 10,
                'user_personal' => 10,
                'requires_confirmation' => true,
            ],
            $this->service()->preview($organization, $user, 'brand_research'),
        );

        // Nada foi debitado: preview é projeção, não operação.
        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'plan_credits_balance' => 30,
            'purchased_credits_balance' => 10,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'purchased_credits_balance' => 200,
        ]);
    }

    /**
     * Quando as carteiras da organização bastam, não há pergunta a fazer.
     */
    public function test_preview_does_not_require_confirmation_when_the_organization_pays(): void
    {
        $organization = $this->organizacao(planCredits: 3, purchasedCredits: 10);
        $user = $this->membro($organization, carteiraPessoal: 200);

        $this->assertSame(
            [
                'cost' => self::COST,
                'org_plan' => 3,
                'org_purchased' => 2,
                'user_personal' => 0,
                'requires_confirmation' => false,
            ],
            $this->service()->preview($organization, $user, self::ACTION),
        );
    }

    /**
     * O bloqueio é do backend, não do modal: uma chamada que resultaria em
     * débito pessoal e não traz a flag é recusada aqui (SAD 12.19).
     */
    public function test_deduct_throws_409_without_confirmation_flag(): void
    {
        $organization = $this->organizacao(planCredits: 3);
        $user = $this->membro($organization, carteiraPessoal: 100);

        try {
            $this->service()->deduct($organization, $user, self::ACTION);
            $this->fail('PersonalCreditsConfirmationRequiredException was not thrown.');
        } catch (PersonalCreditsConfirmationRequiredException $e) {
            $this->assertSame([
                'total_cost' => self::COST,
                'organization_plan_credits' => 3,
                'organization_purchased_credits' => 0,
                'personal_credits' => 2,
            ], $e->breakdown);
        }

        // A pergunta não debita: o rollback não deixa nada para trás.
        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'plan_credits_balance' => 3,
            'plan_credits_used_this_month' => 0,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'purchased_credits_balance' => 100,
        ]);

        // E o mesmo vale para a verificação otimista, que responde antes de
        // qualquer transação.
        $this->expectException(PersonalCreditsConfirmationRequiredException::class);
        $this->service()->canExecute($organization, $user, self::ACTION);
    }

    public function test_deduct_succeeds_with_confirmation_flag(): void
    {
        $organization = $this->organizacao(planCredits: 3);
        $user = $this->membro($organization, carteiraPessoal: 100);

        $this->service()->deduct($organization, $user, self::ACTION, confirmPersonalCredits: true);

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'plan_credits_balance' => 0,
            'purchased_credits_balance' => 0,
            'plan_credits_used_this_month' => 3,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'purchased_credits_balance' => 98,
        ]);
    }

    /**
     * A flag autoriza a carteira, não um valor (SAD 12.19).
     *
     * Entre o 409 e a confirmação, outro membro da organização pode ter gasto —
     * ou um Admin pode ter comprado — créditos da org. O valor real é
     * recalculado dentro da transação e pode ser menor, maior ou nenhum.
     */
    public function test_confirmation_authorizes_action_not_exact_amount(): void
    {
        // Cenário A — a carteira pessoal deixa de ser necessária: a flag
        // autoriza, não obriga.
        $organizationA = $this->organizacao(planCredits: 3);
        $userA = $this->membro($organizationA, carteiraPessoal: 100);

        $this->assertSame(2, $this->service()->preview($organizationA, $userA, self::ACTION)['user_personal']);

        // Um Admin compra créditos para a organização depois do aviso.
        DB::table('organizations')->where('id', $organizationA->id)
            ->update(['purchased_credits_balance' => 50]);

        $this->service()->deduct($organizationA, $userA, self::ACTION, confirmPersonalCredits: true);

        $this->assertDatabaseHas('users', [
            'id' => $userA->id,
            'purchased_credits_balance' => 100,
        ]);
        $this->assertDatabaseHas('organizations', [
            'id' => $organizationA->id,
            'plan_credits_balance' => 0,
            'purchased_credits_balance' => 48,
        ]);

        // Cenário B — a parcela pessoal cresce: outro membro consumiu o saldo
        // da organização entre o aviso e a confirmação.
        $organizationB = $this->organizacao(planCredits: 3);
        $userB = $this->membro($organizationB, carteiraPessoal: 100);

        $this->assertSame(2, $this->service()->preview($organizationB, $userB, self::ACTION)['user_personal']);

        DB::table('organizations')->where('id', $organizationB->id)
            ->update(['plan_credits_balance' => 0]);

        $this->service()->deduct($organizationB, $userB, self::ACTION, confirmPersonalCredits: true);

        // Cinco créditos pessoais, e não os dois anunciados.
        $this->assertDatabaseHas('users', [
            'id' => $userB->id,
            'purchased_credits_balance' => 95,
        ]);
        $this->assertDatabaseHas('organizations', [
            'id' => $organizationB->id,
            'plan_credits_balance' => 0,
            'plan_credits_used_this_month' => 0,
        ]);
    }

    // ---------------------------------------------------------------------
    // Transação, lock e fonte da verdade (SAD 12.16 e N.5)
    // ---------------------------------------------------------------------

    public function test_deduct_decrements_balance_in_transaction(): void
    {
        $organization = $this->organizacao(planCredits: 100);
        $user = $this->membro($organization);

        // O débito precisa ocorrer em um nível de transação mais profundo que o
        // do chamador: é isso que garante que leitura do saldo e escrita sejam
        // atômicas diante de execuções concorrentes (SAD N.5).
        $nivelDoChamador = DB::transactionLevel();
        $niveisDaEscrita = [];
        $commits = 0;

        DB::listen(function (QueryExecuted $query) use (&$niveisDaEscrita): void {
            if (str_contains($query->sql, 'update "organizations"')) {
                $niveisDaEscrita[] = DB::transactionLevel();
            }
        });

        Event::listen(TransactionCommitted::class, function () use (&$commits): void {
            $commits++;
        });

        $this->service()->deduct($organization, $user, self::ACTION);

        $this->assertNotEmpty($niveisDaEscrita, 'Nenhuma escrita em organizations foi observada.');
        $this->assertGreaterThan($nivelDoChamador, min($niveisDaEscrita));
        $this->assertGreaterThan(0, $commits);
        $this->assertSame($nivelDoChamador, DB::transactionLevel());

        $this->assertSame(100 - self::COST, (int) $organization->plan_credits_balance);
        $this->assertSame(self::COST, (int) $organization->plan_credits_used_this_month);
        $this->assertFalse($organization->isDirty([
            'plan_credits_balance',
            'purchased_credits_balance',
            'plan_credits_used_this_month',
        ]));
        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'plan_credits_balance' => 100 - self::COST,
            'purchased_credits_balance' => 0,
            'plan_credits_used_this_month' => self::COST,
        ]);
    }

    /**
     * A linha do usuário só entra no lock quando a carteira pessoal seria
     * tocada (SAD 12.3): um débito que a organização paga sozinha não escreve
     * em `users`.
     */
    public function test_deduct_does_not_touch_users_when_the_organization_pays(): void
    {
        $organization = $this->organizacao(planCredits: 100);
        $user = $this->membro($organization, carteiraPessoal: 100);

        $escritasEmUsers = 0;

        DB::listen(function (QueryExecuted $query) use (&$escritasEmUsers): void {
            if (str_contains($query->sql, 'update "users"')) {
                $escritasEmUsers++;
            }
        });

        $this->service()->deduct($organization, $user, self::ACTION);

        $this->assertSame(0, $escritasEmUsers);
    }

    public function test_deduct_reads_the_balance_from_the_source_of_truth(): void
    {
        $organization = $this->organizacao(planCredits: 100);
        $user = $this->membro($organization);

        // Simula outro membro da mesma organização consumindo o saldo entre a
        // verificação e o débito. A instância em memória segue defasada.
        DB::table('organizations')->where('id', $organization->id)
            ->update(['plan_credits_balance' => 10]);

        $this->service()->deduct($organization, $user, self::ACTION);

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'plan_credits_balance' => 10 - self::COST,
        ]);
    }

    public function test_deduct_rejects_a_stale_balance_that_no_longer_covers_the_cost(): void
    {
        $organization = $this->organizacao(planCredits: 100);
        $user = $this->membro($organization);

        DB::table('organizations')->where('id', $organization->id)
            ->update(['plan_credits_balance' => self::COST - 1]);

        $this->expectException(InsufficientCreditsException::class);

        $this->service()->deduct($organization, $user, self::ACTION);
    }

    public function test_deduct_accumulates_the_monthly_consumption(): void
    {
        $organization = $this->organizacao(planCredits: 100);
        $organization->forceFill(['plan_credits_used_this_month' => 12])->save();
        $user = $this->membro($organization);

        $this->service()->deduct($organization, $user, self::ACTION);
        $this->service()->deduct($organization, $user, self::ACTION);

        // Saldo do plano e consumo do mês descrevem o mesmo débito por ângulos
        // opostos.
        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'plan_credits_balance' => 100 - (2 * self::COST),
            'plan_credits_used_this_month' => 12 + (2 * self::COST),
        ]);
    }

    public function test_deduct_uses_the_cost_registered_in_plan_limits(): void
    {
        $this->definirCusto('brand_research', 50);

        $organization = $this->organizacao(planCredits: 60);

        $this->service()->deduct($organization, $this->membro($organization), 'brand_research');

        $this->assertSame(10, (int) $organization->plan_credits_balance);
    }

    public function test_cost_is_cached_under_the_official_plan_limits_key(): void
    {
        $organization = $this->organizacao(planCredits: self::COST);

        $this->service()->canExecute($organization, $this->membro($organization), self::ACTION);

        $this->assertSame(self::COST, Cache::get('plan_limits:'.self::ACTION));
    }

    // ---------------------------------------------------------------------
    // Leitura de saldo e plano sem teto (SAD 12.2 e 12.6)
    // ---------------------------------------------------------------------

    public function test_get_balances_returns_the_three_wallets_and_the_sum(): void
    {
        $organization = $this->organizacao(planCredits: 42, purchasedCredits: 8);
        $user = $this->membro($organization, carteiraPessoal: 7);

        $this->assertSame(
            [
                'org_plan' => 42,
                'org_purchased' => 8,
                'user_personal' => 7,
                'total' => 57,
            ],
            $this->service()->getBalances($organization, $user),
        );
    }

    /**
     * Custo nulo em `plan_limits` é ausência de teto: nenhuma carteira é
     * consultada nem debitada, e a carteira pessoal não financia o ilimitado.
     *
     * `plan_limits.credits = null` (custo sem teto) e `organizations.plan = null`
     * (sem assinatura) são conceitos distintos: aqui a organização tem plano.
     */
    public function test_enterprise_unlimited_still_works(): void
    {
        $service = $this->serviceSemTeto();

        $organization = $this->organizacao(planCredits: 0, purchasedCredits: 25, plan: 'full_pass');
        $user = $this->membro($organization, carteiraPessoal: 30);

        $this->assertTrue($service->canExecute($organization, $user, 'brand_research'));

        $service->deduct($organization, $user, 'brand_research');

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'plan_credits_balance' => 0,
            'purchased_credits_balance' => 25,
            'plan_credits_used_this_month' => 0,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'purchased_credits_balance' => 30,
        ]);

        $this->assertSame(
            ['org_plan' => 0, 'org_purchased' => 25, 'user_personal' => 30, 'total' => 55],
            $service->getBalances($organization->refresh(), $user->refresh()),
        );
    }

    /**
     * Sem teto de custo, mas sem contrato: a ausência de plano continua
     * bloqueando (SAD 12.6 — os dois `null` não se confundem).
     */
    public function test_unlimited_cost_does_not_bypass_the_active_plan_check(): void
    {
        $service = $this->serviceSemTeto();

        $organization = $this->organizacao(planCredits: 0, plan: null);

        $this->expectException(NoActivePlanException::class);

        $service->canExecute($organization, $this->membro($organization), 'brand_research');
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
