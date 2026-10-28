<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Mockery\MockInterface;
use Tests\Concerns\InteractsWithOrganizations;
use Tests\TestCase;

/**
 * Ponte Firebase → sessão Laravel das rotas web (Sessão 3.1, SAD 5 e 20).
 *
 * Navegação de browser não envia `Authorization: Bearer`; estas rotas
 * dependem exclusivamente de `auth.web` (sessão), nunca de `auth.firebase`.
 */
class WebAuthTest extends TestCase
{
    use InteractsWithOrganizations, RefreshDatabase;

    private const FIREBASE_UID = 'firebase-uid-web-123';

    /**
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

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $this->get('/briefing')
            ->assertRedirect(route('login'));
    }

    public function test_firebase_token_creates_session_and_reuses_sync_action(): void
    {
        $this->mockFirebaseToken();

        $response = $this->postJson('/login/firebase', ['token' => 'valid-id-token']);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'redirect' => route('dashboard'),
            ]);

        // A Action reutilizada é a mesma da API: mesmo `firebase_uid`, mesmo
        // registro local (CLAUDE.md regra 8).
        $this->assertDatabaseHas('users', [
            'firebase_uid' => self::FIREBASE_UID,
            'email' => 'nicolas@tinyailab.test',
        ]);

        $this->assertTrue(Auth::check());
        $this->assertSame(self::FIREBASE_UID, Auth::user()->firebase_uid);
    }

    public function test_new_user_lands_with_personal_organization_active(): void
    {
        $this->mockFirebaseToken();

        $this->postJson('/login/firebase', ['token' => 'valid-id-token'])
            ->assertOk();

        // A sessão criada no login já basta para uma rota atrás de
        // ['auth.web', 'organization']: a organização Personal automática do
        // primeiro login (SAD 20.3) é a única, então o contexto se resolve
        // sozinho, sem seletor.
        $this->get('/briefing')->assertOk();

        $user = User::where('firebase_uid', self::FIREBASE_UID)->firstOrFail();

        $this->assertSame(1, $user->organizations()->count());

        $personal = $user->organizations()->first();

        $this->assertSame('Personal', $personal->name);

        // A organização Personal do primeiro login tem o usuário como Owner
        // (SAD 20.3, ADR-010).
        $this->assertSame(OrganizationRole::Owner, $user->roleIn($personal));
    }

    public function test_organizations_routes_accessible_without_active_org(): void
    {
        // Membro de nenhuma organização: sem a organização Personal
        // automática, para simular exatamente o estado que essas telas
        // existem para resolver (SAD 20.2).
        $user = User::factory()->create(['firebase_uid' => self::FIREBASE_UID]);

        $this->actingAs($user);

        $this->get('/organizations/create')->assertOk();
        $this->get('/organizations/select')->assertOk();
    }

    public function test_organizations_routes_redirect_to_login_when_unauthenticated(): void
    {
        $this->get('/organizations/create')->assertRedirect(route('login'));
        $this->get('/organizations/select')->assertRedirect(route('login'));
    }
}
