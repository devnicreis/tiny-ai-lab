<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Exceptions\InsufficientOrganizationRoleException;
use App\Exceptions\OwnerRoleRequiresTransferException;
use App\Exceptions\PendingInvitationExistsException;
use App\Mail\OrganizationInvitationMail;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Services\FirebaseService;
use App\Services\OrganizationMembershipService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery\MockInterface;
use Tests\Concerns\InteractsWithOrganizations;
use Tests\TestCase;

/**
 * Convites de organização (SAD 20.4): quem convida quem, um convite pendente
 * por e-mail e as regras de segurança do aceite.
 */
class OrganizationInvitationTest extends TestCase
{
    use InteractsWithOrganizations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function membership(): OrganizationMembershipService
    {
        return app(OrganizationMembershipService::class);
    }

    /**
     * Autentica como o usuário com as claims informadas — `email_verified` e
     * `email` vêm do token, não do banco.
     *
     * @param  array<string, mixed>  $claims
     * @return array<string, string>
     */
    private function headersWithClaims(User $user, array $claims = []): array
    {
        $this->mock(FirebaseService::class, function (MockInterface $mock) use ($user, $claims) {
            $mock->shouldReceive('verifyIdToken')->andReturn([
                'sub' => $user->firebase_uid,
                'email' => $user->email,
                'name' => $user->name,
                'email_verified' => true,
                ...$claims,
            ]);
        });

        return ['Authorization' => 'Bearer valid-id-token'];
    }

    /**
     * @return array{Organization, User, string}
     */
    private function invitationFor(string $email, OrganizationRole $role = OrganizationRole::Member): array
    {
        $owner = User::factory()->create();
        $organization = $this->organizationWith($owner);

        $issued = $this->membership()->invite($organization, $owner, $email, $role);

        return [$organization, $owner, $issued['token']];
    }

    // ---------------------------------------------------------------------
    // Aceite
    // ---------------------------------------------------------------------

    public function test_user_with_verified_matching_email_accepts_invitation(): void
    {
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        [$organization, , $token] = $this->invitationFor('Invitee@Example.com', OrganizationRole::Admin);

        $this->postJson("/api/invitations/{$token}/accept", [], $this->headersWithClaims($invitee))
            ->assertOk()
            ->assertJsonPath('organization.id', $organization->id)
            ->assertJsonPath('organization.role', 'admin');

        $this->assertSame(OrganizationRole::Admin, $invitee->roleIn($organization));
        $this->assertNotNull($organization->invitations()->first()->accepted_at);
    }

    public function test_unverified_email_cannot_accept(): void
    {
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        [$organization, , $token] = $this->invitationFor('invitee@example.com');

        // O banco diz "verificado", mas o token atual não: vale o token.
        $this->postJson(
            "/api/invitations/{$token}/accept",
            [],
            $this->headersWithClaims($invitee, ['email_verified' => false]),
        )
            ->assertForbidden()
            ->assertJsonPath('error', 'email_not_verified');

        $this->assertFalse($invitee->belongsToOrganization($organization));
        $this->assertNull($organization->invitations()->first()->accepted_at);
    }

    public function test_different_email_cannot_accept(): void
    {
        $intruder = User::factory()->create(['email' => 'intruder@example.com']);
        [$organization, , $token] = $this->invitationFor('invitee@example.com');

        $this->postJson("/api/invitations/{$token}/accept", [], $this->headersWithClaims($intruder))
            ->assertForbidden()
            ->assertJsonPath('error', 'invitation_email_mismatch');

        $this->assertFalse($intruder->belongsToOrganization($organization));
    }

    public function test_expired_invitation_cannot_be_accepted(): void
    {
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        [$organization, , $token] = $this->invitationFor('invitee@example.com');

        $this->travel(OrganizationMembershipService::INVITATION_TTL_DAYS + 1)->days();

        $this->postJson("/api/invitations/{$token}/accept", [], $this->headersWithClaims($invitee))
            ->assertStatus(410)
            ->assertJsonPath('error', 'invitation_expired');

        $this->assertFalse($invitee->belongsToOrganization($organization));
    }

    public function test_revoked_invitation_cannot_be_accepted(): void
    {
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        [$organization, $owner, $token] = $this->invitationFor('invitee@example.com');

        $this->membership()->revokeInvitation($organization->invitations()->first(), $owner);

        $this->postJson("/api/invitations/{$token}/accept", [], $this->headersWithClaims($invitee))
            ->assertStatus(410)
            ->assertJsonPath('error', 'invitation_revoked');

        $this->assertFalse($invitee->belongsToOrganization($organization));
    }

    public function test_unknown_token_is_not_found(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/invitations/not-a-real-token/accept', [], $this->headersWithClaims($user))
            ->assertNotFound()
            ->assertJsonPath('error', 'invitation_not_found');
    }

    public function test_accepted_invitation_cannot_be_reused_after_removal(): void
    {
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        [$organization, $owner, $token] = $this->invitationFor('invitee@example.com');

        $this->membership()->acceptInvitation($invitee, $token);
        $this->membership()->removeMember($organization, $owner, $invitee);

        $this->postJson("/api/invitations/{$token}/accept", [], $this->headersWithClaims($invitee))
            ->assertConflict()
            ->assertJsonPath('error', 'invitation_already_accepted');

        $this->assertFalse($invitee->belongsToOrganization($organization));
    }

    /**
     * Aceitar sendo já membro não muda o papel — nem o rebaixa, nem o
     * promove — e encerra o convite.
     */
    public function test_accepting_while_already_member_is_idempotent(): void
    {
        $owner = User::factory()->create();
        $organization = $this->organizationWith($owner);
        $issued = $this->membership()->invite($organization, $owner, 'invitee@example.com', OrganizationRole::Member);

        $invitee = $this->adminOf($organization, ['email' => 'invitee@example.com']);

        $this->membership()->acceptInvitation($invitee, $issued['token']);
        $this->membership()->acceptInvitation($invitee, $issued['token']);

        $this->assertSame(OrganizationRole::Admin, $invitee->roleIn($organization));
        $this->assertSame(1, $organization->users()->whereKey($invitee->id)->count());
        $this->assertNotNull($issued['invitation']->fresh()->accepted_at);
    }

    // ---------------------------------------------------------------------
    // Convite
    // ---------------------------------------------------------------------

    public function test_only_one_pending_invitation_per_email_per_organization(): void
    {
        $owner = User::factory()->create();
        $organization = $this->organizationWith($owner);

        $this->membership()->invite($organization, $owner, 'invitee@example.com', OrganizationRole::Member);

        try {
            $this->membership()->invite($organization, $owner, 'INVITEE@example.com', OrganizationRole::Admin);
            $this->fail('A second pending invitation for the same email was accepted.');
        } catch (PendingInvitationExistsException) {
        }

        // A barreira do banco vale mesmo para uma escrita fora do domínio.
        try {
            OrganizationInvitation::factory()->for($organization)->create(['email' => 'Invitee@Example.com']);
            $this->fail('The database accepted a second pending invitation.');
        } catch (UniqueConstraintViolationException) {
        }

        // Outra organização convida o mesmo e-mail livremente.
        $other = $this->organizationWith($owner);
        $this->membership()->invite($other, $owner, 'invitee@example.com', OrganizationRole::Member);

        // Revogado, o e-mail pode ser convidado de novo.
        $this->membership()->revokeInvitation($organization->invitations()->first(), $owner);
        $this->membership()->invite($organization, $owner, 'invitee@example.com', OrganizationRole::Member);

        $this->assertSame(1, $organization->invitations()->pending()->count());
    }

    public function test_expired_pending_invitation_does_not_block_a_new_one(): void
    {
        $owner = User::factory()->create();
        $organization = $this->organizationWith($owner);

        $first = $this->membership()->invite($organization, $owner, 'invitee@example.com', OrganizationRole::Member);

        $this->travel(OrganizationMembershipService::INVITATION_TTL_DAYS + 1)->days();

        $this->membership()->invite($organization, $owner, 'invitee@example.com', OrganizationRole::Member);

        $this->assertNotNull($first['invitation']->fresh()->revoked_at);
        $this->assertSame(1, $organization->invitations()->pending()->count());
    }

    public function test_admin_cannot_invite_admin(): void
    {
        $organization = $this->organizationWith(User::factory()->create());
        $admin = $this->adminOf($organization);

        $this->expectException(InsufficientOrganizationRoleException::class);

        try {
            $this->membership()->invite($organization, $admin, 'invitee@example.com', OrganizationRole::Admin);
        } finally {
            $this->assertSame(0, $organization->invitations()->count());
        }
    }

    public function test_invite_respects_role_matrix(): void
    {
        $owner = User::factory()->create();
        $organization = $this->organizationWith($owner);
        $admin = $this->adminOf($organization);
        $member = $this->memberOf($organization);

        $this->membership()->invite($organization, $owner, 'a@example.com', OrganizationRole::Admin);
        $this->membership()->invite($organization, $admin, 'b@example.com', OrganizationRole::Member);

        try {
            $this->membership()->invite($organization, $member, 'c@example.com', OrganizationRole::Member);
            $this->fail('A Member was able to invite.');
        } catch (InsufficientOrganizationRoleException) {
        }

        try {
            $this->membership()->invite($organization, $owner, 'd@example.com', OrganizationRole::Owner);
            $this->fail('An invitation with the owner role was issued.');
        } catch (OwnerRoleRequiresTransferException) {
        }

        $this->assertSame(2, $organization->invitations()->count());
    }

    public function test_invite_stores_only_the_token_hash_and_queues_the_email(): void
    {
        $owner = User::factory()->create();
        $organization = $this->organizationWith($owner);

        $issued = $this->membership()->invite($organization, $owner, 'invitee@example.com', OrganizationRole::Member);

        $this->assertSame(hash('sha256', $issued['token']), $issued['invitation']->token_hash);
        $this->assertDatabaseMissing('organization_invitations', ['token_hash' => $issued['token']]);
        $this->assertStringEndsWith('/invitations/'.$issued['token'], $issued['url']);

        Mail::assertQueued(
            OrganizationInvitationMail::class,
            fn (OrganizationInvitationMail $mail) => $mail->hasTo('invitee@example.com')
                && $mail->queue === 'notifications'
                && $mail->acceptUrl === $issued['url'],
        );
    }
}
