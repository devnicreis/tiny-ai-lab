<?php

namespace Tests\Feature;

use App\Models\Execution;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use Mockery\MockInterface;
use Tests\Concerns\InteractsWithOrganizations;
use Tests\TestCase;

/**
 * A rota /broadcasting/auth não usa sessão PHP: sua identidade vem do Firebase
 * ID Token, igual às demais rotas protegidas. Estes testes cobrem tanto o
 * middleware quanto a autorização do canal privado `execution.{id}`.
 */
class BroadcastAuthTest extends TestCase
{
    use InteractsWithOrganizations, RefreshDatabase;

    private const FIREBASE_UID = 'firebase-uid-broadcast';

    private const SOCKET_ID = '123.456';

    protected function setUp(): void
    {
        parent::setUp();

        // O ambiente de testes usa o broadcaster `null`, que aceita qualquer
        // canal sem avaliar os callbacks. Trocamos para o Reverb — que assina a
        // resposta localmente, sem rede — e reregistramos os canais, já que o
        // Broadcast::channel() os guarda na instância do driver.
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-app-key',
            'broadcasting.connections.reverb.secret' => 'test-app-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app-id',
        ]);

        Broadcast::purge('reverb');

        require base_path('routes/channels.php');
    }

    private function mockFirebaseToken(string $uid = self::FIREBASE_UID): void
    {
        $this->mock(FirebaseService::class, function (MockInterface $mock) use ($uid) {
            $mock->shouldReceive('verifyIdToken')->andReturn([
                'sub' => $uid,
                'email' => 'nicolas@tinyailab.test',
                'name' => 'Nicolas Reis',
                'email_verified' => true,
            ]);
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
     * Execution em uma Brand da organização do usuário (SAD 20.6).
     */
    private function executionVisibleTo(User $user): Execution
    {
        return Execution::factory()
            ->for($this->brandOf($this->organizationWith($user)))
            ->create(['user_id' => $user->id]);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function authorize(string $channel, array $headers = []): TestResponse
    {
        return $this->postJson('/broadcasting/auth', [
            'socket_id' => self::SOCKET_ID,
            'channel_name' => $channel,
        ], $headers);
    }

    public function test_broadcasting_auth_requires_valid_firebase_token(): void
    {
        $user = User::factory()->create(['firebase_uid' => self::FIREBASE_UID]);
        $execution = $this->executionVisibleTo($user);

        $this->mockFirebaseToken();

        $this->authorize("private-execution.{$execution->id}", [
            'Authorization' => 'Bearer valid-id-token',
        ])
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_broadcasting_auth_rejects_invalid_token(): void
    {
        $user = User::factory()->create(['firebase_uid' => self::FIREBASE_UID]);
        $execution = $this->executionVisibleTo($user);

        $this->mockInvalidFirebaseToken();

        $this->authorize("private-execution.{$execution->id}", [
            'Authorization' => 'Bearer broken-id-token',
        ])
            ->assertUnauthorized()
            ->assertExactJson(['error' => 'unauthenticated']);
    }

    public function test_broadcasting_auth_rejects_missing_token(): void
    {
        $user = User::factory()->create(['firebase_uid' => self::FIREBASE_UID]);
        $execution = $this->executionVisibleTo($user);

        $this->authorize("private-execution.{$execution->id}")
            ->assertUnauthorized()
            ->assertExactJson(['error' => 'unauthenticated']);
    }

    public function test_broadcasting_auth_rejects_execution_of_another_organization(): void
    {
        $user = User::factory()->create(['firebase_uid' => self::FIREBASE_UID]);
        $this->organizationWith($user);

        $otherUser = User::factory()->create(['firebase_uid' => 'firebase-uid-outro']);
        $foreignExecution = $this->executionVisibleTo($otherUser);

        $this->mockFirebaseToken();

        $this->authorize("private-execution.{$foreignExecution->id}", [
            'Authorization' => 'Bearer valid-id-token',
        ])->assertForbidden();
    }
}
