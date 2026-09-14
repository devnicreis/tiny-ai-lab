<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Mockery\MockInterface;
use Tests\Concerns\InteractsWithOrganizations;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use InteractsWithOrganizations, RefreshDatabase;

    private const FIREBASE_UID = 'firebase-uid-123';

    /**
     * Substitui o FirebaseService no container para que os testes nunca
     * dependam do Firebase real.
     *
     * @param  array<string, mixed>  $claims
     */
    private function mockFirebaseToken(array $claims = []): void
    {
        $this->mock(FirebaseService::class, function (MockInterface $mock) use ($claims) {
            $mock->shouldReceive('verifyIdToken')->andReturn(array_merge([
                'sub' => self::FIREBASE_UID,
                'email' => 'nicolas@tinyailab.test',
                'name' => 'Nicolas Reis',
                'email_verified' => true,
            ], $claims));
        });
    }

    private function mockInvalidFirebaseToken(): void
    {
        $this->mock(FirebaseService::class, function (MockInterface $mock) {
            $mock->shouldReceive('verifyIdToken')
                ->andThrow(new InvalidArgumentException('Invalid Firebase ID token.'));
        });
    }

    /**
     * Identidade, participações e contexto ativo viajam separados (SAD 20).
     *
     * O primeiro login sincroniza o usuário e cria sua organização "Personal"
     * automática (SAD 20.3): a lista de organizações nunca vem vazia, e como é
     * a única, o contexto ativo já é resolvido sozinho — sem seletor
     * (SAD 20.3, "Seletor de organização na UI").
     */
    public function test_valid_firebase_token_creates_and_returns_user(): void
    {
        $this->mockFirebaseToken();

        $response = $this->postJson('/api/auth/firebase', ['token' => 'valid-id-token']);

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'email', 'name', 'is_admin', 'purchased_credits_balance'],
                'organizations',
                'active_organization',
            ])
            ->assertJsonPath('user.email', 'nicolas@tinyailab.test')
            ->assertJsonPath('user.name', 'Nicolas Reis')
            ->assertJsonPath('user.is_admin', false)
            ->assertJsonPath('user.purchased_credits_balance', 0)
            ->assertJsonCount(1, 'organizations')
            ->assertJsonPath('organizations.0.name', 'Personal')
            ->assertJsonPath('organizations.0.role', 'admin')
            // Uma organização apenas: já é o contexto ativo, sem escolha
            // pendente (SAD 20.5).
            ->assertJsonPath('active_organization.name', 'Personal')
            ->assertJsonPath('active_organization.plan', null);

        // A carteira pessoal é do usuário; plano e carteiras da organização não
        // aparecem em `user` porque não lhe pertencem (SAD 12.1).
        $response->assertJsonMissingPath('user.plan');
        $response->assertJsonMissingPath('user.plan_credits_balance');

        $this->assertDatabaseHas('users', [
            'firebase_uid' => self::FIREBASE_UID,
            'email' => 'nicolas@tinyailab.test',
        ]);
    }

    public function test_invalid_token_returns_401(): void
    {
        $this->mockInvalidFirebaseToken();

        $this->postJson('/api/auth/firebase', ['token' => 'broken-id-token'])
            ->assertUnauthorized()
            ->assertExactJson(['error' => 'unauthenticated']);

        $this->getJson('/api/auth/me', ['Authorization' => 'Bearer broken-id-token'])
            ->assertUnauthorized()
            ->assertExactJson(['error' => 'unauthenticated']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_missing_token_returns_401(): void
    {
        $this->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertExactJson(['error' => 'unauthenticated']);
    }

    public function test_me_returns_user_organizations_and_active_context(): void
    {
        $user = User::factory()->create([
            'firebase_uid' => self::FIREBASE_UID,
            'email' => 'nicolas@tinyailab.test',
            'name' => 'Nicolas Reis',
            'purchased_credits_balance' => 120,
        ]);

        $organization = $this->organizationWith($user, OrganizationRole::Admin, [
            'name' => 'Arkion',
            'slug' => 'arkion',
            'plan' => 'studio_visual',
            'plan_credits_balance' => 10_000,
            'purchased_credits_balance' => 500,
        ]);

        $this->mockFirebaseToken();

        $this->getJson('/api/auth/me', ['Authorization' => 'Bearer valid-id-token'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', 'nicolas@tinyailab.test')
            ->assertJsonPath('user.is_admin', false)
            // Carteira pessoal — o único saldo do usuário (SAD 12.2).
            ->assertJsonPath('user.purchased_credits_balance', 120)
            ->assertJsonPath('organizations.0.id', $organization->id)
            ->assertJsonPath('organizations.0.name', 'Arkion')
            ->assertJsonPath('organizations.0.slug', 'arkion')
            // Papel na organização — outro eixo, jamais `is_admin` (SAD 20.7).
            ->assertJsonPath('organizations.0.role', 'admin')
            ->assertJsonPath('active_organization.id', $organization->id)
            ->assertJsonPath('active_organization.plan', 'studio_visual')
            // As carteiras da organização, separadas e nunca somadas
            // (CLAUDE.md regra 27).
            ->assertJsonPath('active_organization.plan_credits_balance', 10_000)
            ->assertJsonPath('active_organization.purchased_credits_balance', 500);

        // O middleware sincroniza o usuário existente em vez de duplicá-lo.
        $this->assertDatabaseCount('users', 1);
    }

    /**
     * Com duas organizações e nenhuma escolhida não há contexto ativo: decidir
     * por ele seria escolher de qual carteira sai o próximo crédito (SAD 20.5).
     */
    public function test_me_leaves_active_organization_null_when_the_user_must_choose(): void
    {
        $user = User::factory()->create(['firebase_uid' => self::FIREBASE_UID]);

        $this->organizationWith($user);
        $this->organizationWith($user);

        $this->mockFirebaseToken();

        $this->getJson('/api/auth/me', ['Authorization' => 'Bearer valid-id-token'])
            ->assertOk()
            ->assertJsonCount(2, 'organizations')
            ->assertJsonPath('active_organization', null);
    }

    /**
     * Staff da plataforma é eixo global e não concede papel algum em nenhuma
     * organização (SAD 20.7).
     */
    public function test_platform_staff_flag_is_independent_from_organization_role(): void
    {
        $user = User::factory()->create([
            'firebase_uid' => self::FIREBASE_UID,
            'is_admin' => true,
        ]);

        $this->organizationWith($user, OrganizationRole::Member);

        $this->mockFirebaseToken();

        $this->getJson('/api/auth/me', ['Authorization' => 'Bearer valid-id-token'])
            ->assertOk()
            ->assertJsonPath('user.is_admin', true)
            ->assertJsonPath('organizations.0.role', 'member');
    }
}
