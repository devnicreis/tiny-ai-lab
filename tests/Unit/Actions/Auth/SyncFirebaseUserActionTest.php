<?php

namespace Tests\Unit\Actions\Auth;

use App\Actions\Auth\SyncFirebaseUserAction;
use App\Enums\OrganizationRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Organização "Personal" automática do primeiro login (SAD 20.3).
 */
class SyncFirebaseUserActionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function claims(array $overrides = []): array
    {
        return array_merge([
            'sub' => 'firebase-uid-new-user',
            'email' => 'nicolas@tinyailab.test',
            'name' => 'Nicolas Reis',
            'email_verified' => true,
        ], $overrides);
    }

    public function test_new_user_receives_automatic_personal_organization(): void
    {
        $user = app(SyncFirebaseUserAction::class)->handle($this->claims());

        $this->assertCount(1, $user->organizations);

        $organization = $user->organizations->first();

        $this->assertSame('Personal', $organization->name);
        $this->assertSame(OrganizationRole::Owner->value, $organization->pivot->role);
        // Nasce sem plano, como qualquer organização (SAD 12.18).
        $this->assertNull($organization->plan);
    }

    /**
     * Vários usuários recebem, cada um, sua própria organização "Personal": o
     * nome é apresentação, não identidade — o slug garante que não colidam.
     */
    public function test_personal_organization_slug_is_unique_and_never_bare(): void
    {
        $userA = app(SyncFirebaseUserAction::class)->handle($this->claims(['sub' => 'uid-a', 'email' => 'a@tinyailab.test']));
        $userB = app(SyncFirebaseUserAction::class)->handle($this->claims(['sub' => 'uid-b', 'email' => 'b@tinyailab.test']));

        $slugA = $userA->organizations->first()->slug;
        $slugB = $userB->organizations->first()->slug;

        $this->assertNotSame($slugA, $slugB);
        $this->assertNotSame('personal', $slugA);
        $this->assertNotSame('personal', $slugB);
        $this->assertStringStartsWith('personal-', $slugA);
        $this->assertStringStartsWith('personal-', $slugB);
    }

    /**
     * Um login subsequente do mesmo `firebase_uid` não cria uma segunda
     * organização — a regra é do primeiro login, não de todo `handle()`.
     */
    public function test_existing_user_does_not_receive_another_organization_on_subsequent_login(): void
    {
        $action = app(SyncFirebaseUserAction::class);

        $first = $action->handle($this->claims());
        $this->assertCount(1, $first->organizations);

        $second = $action->handle($this->claims(['name' => 'Nicolas R.']));

        $this->assertSame($first->id, $second->id);
        $this->assertCount(1, $second->organizations()->get());
    }

    /**
     * Usuários que já existiam antes desta regra e ainda não têm organização
     * nenhuma não são tocados aqui: o backfill é de migration, não deste
     * Action, para não duplicar a mesma regra em dois lugares.
     */
    public function test_pre_existing_user_without_organization_is_not_backfilled_here(): void
    {
        $user = User::factory()->create(['firebase_uid' => 'uid-pre-existing']);

        $synced = app(SyncFirebaseUserAction::class)->handle($this->claims([
            'sub' => 'uid-pre-existing',
            'email' => $user->email,
        ]));

        $this->assertSame($user->id, $synced->id);
        $this->assertCount(0, $synced->organizations()->get());
    }
}
