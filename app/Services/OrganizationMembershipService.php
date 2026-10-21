<?php

namespace App\Services;

use App\Enums\OrganizationRole;
use App\Exceptions\AlreadyOrganizationMemberException;
use App\Exceptions\InsufficientOrganizationRoleException;
use App\Exceptions\InvalidOwnershipTransferException;
use App\Exceptions\InvitationAlreadyAcceptedException;
use App\Exceptions\InvitationEmailMismatchException;
use App\Exceptions\InvitationEmailNotVerifiedException;
use App\Exceptions\InvitationExpiredException;
use App\Exceptions\InvitationNotFoundException;
use App\Exceptions\InvitationRevokedException;
use App\Exceptions\NotOrganizationMemberException;
use App\Exceptions\OwnerIsImmutableException;
use App\Exceptions\OwnerRoleRequiresTransferException;
use App\Exceptions\PendingInvitationExistsException;
use App\Mail\OrganizationInvitationMail;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use LogicException;

/**
 * Toda mutação de participação e de papel em uma organização (SAD 20.4,
 * ADR-010).
 *
 * Nenhum Controller, componente Livewire, comando Artisan ou painel
 * administrativo escreve em `organization_user` por conta própria: todos passam
 * por aqui, para que as invariantes do Owner valham em toda superfície.
 *
 * ## Por que transação e lock, e não apenas a Policy
 *
 * A Policy é a primeira barreira e responde "este papel pode?". Ela não
 * protege o piso do invariante — a organização nunca fica sem Owner —, porque
 * duas operações simultâneas avaliadas fora da transação passariam ambas. Por
 * isso cada operação:
 *
 * 1. abre uma transação e trava a linha da organização (`lockForUpdate`),
 *    serializando todas as mutações de participação daquela organização;
 * 2. **relê** os papéis do solicitante e do alvo dentro do lock — os objetos
 *    recebidos podem estar desatualizados, e o papel de quem pede é decidido
 *    pelo banco, não pelo chamador;
 * 3. confere, antes do commit, que resta exatamente um Owner.
 *
 * O índice único parcial `UNIQUE(organization_id) WHERE role = 'owner'` é a
 * segunda barreira, independente desta classe: garante o teto mesmo contra uma
 * escrita que não passe por aqui.
 *
 * O papel decide pelos métodos `can*()` de `OrganizationRole`, nunca por
 * comparação de strings. `users.is_admin` não aparece aqui: staff da plataforma
 * não tem papel implícito em organização de cliente (SAD 20.7).
 */
class OrganizationMembershipService
{
    /**
     * Validade de um convite.
     *
     * A SAD não fixa o prazo (fluxo de convite **[A DEFINIR]**, SAD 20.8);
     * sete dias é o valor do MVP.
     */
    public const INVITATION_TTL_DAYS = 7;

    /**
     * Adiciona um membro com papel `admin` ou `member`.
     *
     * Ninguém entra como Owner: a posse só muda por transferência. Quem
     * convida, e com qual papel pode convidar, é decidido por `invite()` com
     * `canManageMemberWithRole()`.
     *
     * @throws OwnerRoleRequiresTransferException
     * @throws AlreadyOrganizationMemberException
     */
    public function addMember(Organization $organization, User $user, OrganizationRole $role): void
    {
        if (! $role->isAssignable()) {
            throw OwnerRoleRequiresTransferException::for($organization);
        }

        $this->withLockedOrganization($organization, function (Organization $locked) use ($user, $role): void {
            if ($locked->hasMember($user)) {
                throw AlreadyOrganizationMemberException::for($locked);
            }

            $locked->users()->attach($user->id, [
                'role' => $role->value,
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Remove um membro da organização.
     *
     * O Owner remove Admins e Members; o Admin, somente Members; o Member,
     * ninguém. O Owner nunca é removido — nem por si mesmo: sair é uma remoção
     * como outra qualquer (SAD 20.4, invariantes 2 e 5).
     *
     * @throws InsufficientOrganizationRoleException
     * @throws NotOrganizationMemberException
     * @throws OwnerIsImmutableException
     */
    public function removeMember(Organization $organization, User $actor, User $target): void
    {
        $this->withLockedOrganization($organization, function (Organization $locked) use ($actor, $target): void {
            $actorRole = $this->actorRole($locked, $actor);
            $targetRole = $this->targetRole($locked, $target);

            if ($locked->isOwner($target)) {
                throw OwnerIsImmutableException::for($locked);
            }

            if (! $actorRole->canManageMemberWithRole($targetRole)) {
                throw InsufficientOrganizationRoleException::for($locked);
            }

            $locked->users()->detach($target->id);
        });
    }

    /**
     * O próprio usuário sai da organização.
     *
     * Admin e Member saem por conta própria. O Owner não: precisa transferir a
     * posse antes (SAD 20.4, invariante 5). O papel é relido dentro do lock —
     * o objeto recebido pode estar desatualizado, por exemplo logo após uma
     * transferência de posse concorrente.
     *
     * @throws NotOrganizationMemberException
     * @throws OwnerIsImmutableException
     */
    public function leave(Organization $organization, User $user): void
    {
        $this->withLockedOrganization($organization, function (Organization $locked) use ($user): void {
            if (! $this->targetRole($locked, $user)->canLeave()) {
                throw OwnerIsImmutableException::cannotLeave($locked);
            }

            $locked->users()->detach($user->id);
        });
    }

    /**
     * Altera o papel de um membro entre `admin` e `member`.
     *
     * Exclusivo do Owner. Nunca para `owner` — isso é transferência de posse —
     * e nunca a partir de `owner`: o Owner não é rebaixado (SAD 20.4).
     *
     * @throws OwnerRoleRequiresTransferException
     * @throws InsufficientOrganizationRoleException
     * @throws NotOrganizationMemberException
     * @throws OwnerIsImmutableException
     */
    public function changeRole(Organization $organization, User $actor, User $target, OrganizationRole $role): void
    {
        if (! $role->isAssignable()) {
            throw OwnerRoleRequiresTransferException::for($organization);
        }

        $this->withLockedOrganization($organization, function (Organization $locked) use ($actor, $target, $role): void {
            $actorRole = $this->actorRole($locked, $actor);
            // O alvo precisa ser membro; o papel dele só importa por ser Owner.
            $this->targetRole($locked, $target);

            if ($locked->isOwner($target)) {
                throw OwnerIsImmutableException::for($locked);
            }

            if (! $actorRole->canAssignRoles()) {
                throw InsufficientOrganizationRoleException::for($locked);
            }

            $this->writeRole($locked, $target, $role);
        });
    }

    /**
     * Transfere a posse a outro membro existente (SAD 20.4, invariante 3).
     *
     * O solicitante precisa ser o Owner **segundo o banco, dentro do lock** —
     * não segundo o objeto recebido. O destino precisa ser outro membro já
     * existente: fora da organização, é preciso convidar primeiro.
     *
     * A ordem das escritas não é estilo: o Owner atual é rebaixado a Admin
     * **antes** de o destino ser promovido. Na ordem inversa, o índice único
     * parcial recusaria a segunda escrita, porque por um instante existiriam
     * dois Owners. Se qualquer passo falhar, a transação desfaz os dois.
     *
     * O aceite do destinatário é **[A DEFINIR]** (SAD 20.4); este método
     * efetiva a troca e serve a qualquer das duas formas.
     *
     * @throws InsufficientOrganizationRoleException
     * @throws InvalidOwnershipTransferException
     * @throws NotOrganizationMemberException
     */
    public function transferOwnership(Organization $organization, User $actor, User $newOwner): void
    {
        $this->withLockedOrganization($organization, function (Organization $locked) use ($actor, $newOwner): void {
            if (! $this->actorRole($locked, $actor)->canTransferOwnership()) {
                throw InsufficientOrganizationRoleException::for($locked);
            }

            if ((int) $newOwner->getKey() === (int) $actor->getKey()) {
                throw InvalidOwnershipTransferException::toSelf($locked);
            }

            // Destino fora da organização: convidar primeiro (SAD 20.4).
            $this->targetRole($locked, $newOwner);

            $this->writeRole($locked, $actor, OrganizationRole::Admin);
            $this->writeRole($locked, $newOwner, OrganizationRole::Owner);
        });
    }

    /**
     * Convida um e-mail para a organização com papel `admin` ou `member`.
     *
     * O Owner convida Admins e Members; o Admin, somente Members; o Member,
     * ninguém (SAD 20.4). Ninguém é convidado como Owner.
     *
     * O token em claro é devolvido **uma única vez**: o banco guarda só o hash
     * sha256. O link volta ao chamador para que a interface o exiba a quem
     * convidou — o provedor SMTP ainda é **[A DEFINIR]**, e o fluxo não pode
     * depender do envio. O e-mail é enfileirado em `notifications` depois do
     * commit, como conveniência.
     *
     * Um convite pendente já expirado para o mesmo e-mail é revogado aqui: ele
     * não pode mais ser aceito e não deve bloquear o novo.
     *
     * @return array{invitation: OrganizationInvitation, token: string, url: string}
     *
     * @throws OwnerRoleRequiresTransferException
     * @throws InsufficientOrganizationRoleException
     * @throws AlreadyOrganizationMemberException
     * @throws PendingInvitationExistsException
     */
    public function invite(Organization $organization, User $actor, string $email, OrganizationRole $role): array
    {
        if (! $role->isAssignable()) {
            throw OwnerRoleRequiresTransferException::for($organization);
        }

        $email = mb_strtolower(trim($email));
        $token = bin2hex(random_bytes(32));

        try {
            $invitation = $this->withLockedOrganization(
                $organization,
                function (Organization $locked) use ($actor, $email, $role, $token): OrganizationInvitation {
                    if (! $this->actorRole($locked, $actor)->canManageMemberWithRole($role)) {
                        throw InsufficientOrganizationRoleException::for($locked);
                    }

                    if ($locked->users()->whereRaw('lower(users.email) = ?', [$email])->exists()) {
                        throw AlreadyOrganizationMemberException::for($locked);
                    }

                    $open = $locked->invitations()
                        ->whereNull('accepted_at')
                        ->whereNull('revoked_at')
                        ->whereRaw('lower(email) = ?', [$email])
                        ->first();

                    if ($open !== null && ! $open->isExpired()) {
                        throw PendingInvitationExistsException::for($locked);
                    }

                    $open?->update(['revoked_at' => now()]);

                    return $locked->invitations()->create([
                        'email' => $email,
                        'role' => $role,
                        'token_hash' => OrganizationInvitation::hashToken($token),
                        'invited_by_user_id' => $actor->id,
                        'expires_at' => now()->addDays(self::INVITATION_TTL_DAYS),
                    ]);
                },
            );
        } catch (UniqueConstraintViolationException) {
            // Corrida entre dois convites para o mesmo e-mail: o índice único
            // parcial é a última barreira.
            throw PendingInvitationExistsException::for($organization);
        }

        $url = $this->invitationUrl($token);

        Mail::to($email)->queue(new OrganizationInvitationMail(
            organizationName: $organization->name,
            inviterName: $actor->name,
            roleLabel: $role->label(),
            acceptUrl: $url,
            expiresAt: $invitation->expires_at,
        ));

        return ['invitation' => $invitation, 'token' => $token, 'url' => $url];
    }

    /**
     * Revoga um convite pendente.
     *
     * Quem revoga precisa poder convidar com o papel do convite. Revogar um
     * convite já revogado é inócuo; um convite aceito não é revogável — a
     * participação já existe e se desfaz por `removeMember()`.
     *
     * @throws InsufficientOrganizationRoleException
     * @throws InvitationAlreadyAcceptedException
     */
    public function revokeInvitation(OrganizationInvitation $invitation, User $actor): void
    {
        $this->withLockedOrganization(
            $invitation->organization,
            function (Organization $locked) use ($invitation, $actor): void {
                $current = $locked->invitations()
                    ->whereKey($invitation->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $this->actorRole($locked, $actor)->canManageMemberWithRole($current->role)) {
                    throw InsufficientOrganizationRoleException::for($locked);
                }

                if ($current->isAccepted()) {
                    throw InvitationAlreadyAcceptedException::for($locked);
                }

                if (! $current->isRevoked()) {
                    $current->update(['revoked_at' => now()]);
                }
            },
        );

        $invitation->refresh();
    }

    /**
     * Aceita um convite em nome do usuário autenticado.
     *
     * O link não basta: o usuário precisa ter o e-mail **verificado pelo
     * Firebase** e igual ao do convite, sem diferenciar maiúsculas. Sem isso,
     * quem se cadastrasse com o e-mail alheio sequestraria o convite.
     * `email_verified_at` espelha a claim `email_verified` do token da própria
     * requisição (`SyncFirebaseUserAction`).
     *
     * Aceitar sendo já membro é idempotente: o papel existente não muda — nem
     * para cima, nem para baixo — e o convite pendente é encerrado.
     *
     * @throws InvitationNotFoundException
     * @throws InvitationEmailNotVerifiedException
     * @throws InvitationEmailMismatchException
     * @throws InvitationRevokedException
     * @throws InvitationExpiredException
     * @throws InvitationAlreadyAcceptedException
     */
    public function acceptInvitation(User $user, string $token): Organization
    {
        $invitation = OrganizationInvitation::query()
            ->where('token_hash', OrganizationInvitation::hashToken($token))
            ->first()
            ?? throw InvitationNotFoundException::make();

        $organization = $invitation->organization
            ?? throw InvitationNotFoundException::make();

        return $this->withLockedOrganization(
            $organization,
            function (Organization $locked) use ($user, $invitation): Organization {
                $current = $locked->invitations()
                    ->whereKey($invitation->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $user->hasVerifiedEmail()) {
                    throw InvitationEmailNotVerifiedException::for($locked);
                }

                if (! $current->isFor($user->email)) {
                    throw InvitationEmailMismatchException::for($locked);
                }

                $isMember = $locked->hasMember($user);

                if ($current->isAccepted()) {
                    return $isMember ? $locked : throw InvitationAlreadyAcceptedException::for($locked);
                }

                if ($current->isRevoked()) {
                    throw InvitationRevokedException::for($locked);
                }

                if ($current->isExpired()) {
                    throw InvitationExpiredException::for($locked);
                }

                if (! $isMember) {
                    $locked->users()->attach($user->id, [
                        'role' => $current->role->value,
                        'joined_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $current->update(['accepted_at' => now()]);

                return $locked;
            },
        );
    }

    /**
     * O link que o convidado abre. A rota web `GET /invitations/{token}` entra
     * na Sessão 3.2, depois da ponte de autenticação web.
     */
    private function invitationUrl(string $token): string
    {
        return url('/invitations/'.$token);
    }

    /**
     * Executa a operação sob transação, com a linha da organização travada, e
     * confere o invariante do Owner antes do commit.
     *
     * @template TResult
     *
     * @param  Closure(Organization): TResult  $operation
     * @return TResult
     */
    private function withLockedOrganization(Organization $organization, Closure $operation): mixed
    {
        return DB::transaction(function () use ($organization, $operation): mixed {
            $locked = Organization::query()
                ->whereKey($organization->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $result = $operation($locked);

            $this->assertExactlyOneOwner($locked);

            return $result;
        });
    }

    /**
     * O piso e o teto do invariante, dentro da transação (SAD 20.4,
     * invariante 1).
     *
     * As guardas de cada operação já impedem que o Owner seja tocado; esta
     * conferência é a rede para o que elas não previram. Falhar aqui desfaz a
     * transação inteira: é defeito, não fluxo de usuário.
     */
    private function assertExactlyOneOwner(Organization $locked): void
    {
        if ($locked->ownerCount() !== 1) {
            throw new LogicException(
                "Organization {$locked->getKey()} would not have exactly one owner."
            );
        }
    }

    /**
     * O papel do solicitante, relido dentro do lock.
     *
     * Quem não participa da organização não tem papel — e sem papel não há
     * capacidade alguma.
     */
    private function actorRole(Organization $locked, User $actor): OrganizationRole
    {
        return $actor->roleIn($locked)
            ?? throw InsufficientOrganizationRoleException::for($locked);
    }

    /**
     * O papel do alvo, relido dentro do lock.
     */
    private function targetRole(Organization $locked, User $target): OrganizationRole
    {
        return $target->roleIn($locked)
            ?? throw NotOrganizationMemberException::for($locked, (int) $target->getKey());
    }

    private function writeRole(Organization $locked, User $user, OrganizationRole $role): void
    {
        $locked->users()->updateExistingPivot($user->id, [
            'role' => $role->value,
            'updated_at' => now(),
        ]);
    }
}
