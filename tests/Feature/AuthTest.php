<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Mockery\MockInterface;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_valid_firebase_token_creates_and_returns_user(): void
    {
        $this->mockFirebaseToken();

        $response = $this->postJson('/api/auth/firebase', ['token' => 'valid-id-token']);

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'email', 'name', 'plan', 'credits_balance'],
            ])
            ->assertJsonPath('user.email', 'nicolas@tinyailab.test')
            ->assertJsonPath('user.name', 'Nicolas Reis')
            ->assertJsonPath('user.plan', 'free')
            ->assertJsonPath('user.credits_balance', 0);

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

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->pro()->create([
            'firebase_uid' => self::FIREBASE_UID,
            'email' => 'nicolas@tinyailab.test',
            'name' => 'Nicolas Reis',
        ]);

        $this->mockFirebaseToken();

        $this->getJson('/api/auth/me', ['Authorization' => 'Bearer valid-id-token'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', 'nicolas@tinyailab.test')
            ->assertJsonPath('user.plan', 'pro')
            ->assertJsonPath('user.credits_balance', 10000);

        // O middleware sincroniza o usuário existente em vez de duplicá-lo.
        $this->assertDatabaseCount('users', 1);
    }
}
