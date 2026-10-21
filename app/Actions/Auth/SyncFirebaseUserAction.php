<?php

namespace App\Actions\Auth;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sincroniza a identidade provada pelo Firebase com o usuário local do domínio.
 *
 * O `firebase_uid` é a única chave de correlação. Atributos de domínio
 * (`plan`, saldos de crédito) pertencem ao Laravel e nunca são sobrescritos
 * por claims vindas do Firebase.
 */
class SyncFirebaseUserAction
{
    /**
     * @param  array<string, mixed>  $claims  claims de um ID Token já verificado
     *
     * @throws InvalidArgumentException se as claims não identificarem o usuário
     */
    public function handle(array $claims): User
    {
        $uid = $claims['sub'] ?? null;
        $email = $claims['email'] ?? null;

        if (! is_string($uid) || $uid === '' || ! is_string($email) || $email === '') {
            throw new InvalidArgumentException('Firebase claims are missing `sub` or `email`.');
        }

        $name = $claims['name'] ?? null;

        $user = User::firstOrNew(['firebase_uid' => $uid]);
        $isNewUser = ! $user->exists;

        $user->email = $email;
        $user->name = is_string($name) && $name !== '' ? $name : Str::before($email, '@');

        if (($claims['email_verified'] ?? false) && $user->email_verified_at === null) {
            $user->email_verified_at = now();
        }

        // A organização Personal só nasce para quem está sendo criado agora
        // (SAD 20.3). Usuários que já existiam antes desta sessão e ainda não
        // têm organização nenhuma não são tocados aqui — esse backfill já foi
        // resolvido pela migration da Sessão 4.0b; recriá-lo neste Action
        // duplicaria a regra em dois lugares.
        //
        // Usuário e organização nascem na mesma transação: se a criação da
        // organização Personal falhar, o usuário não pode ficar persistido sem
        // ela — nenhum usuário existe sem ao menos uma organização (SAD 20.3).
        if ($isNewUser) {
            DB::transaction(function () use ($user): void {
                $user->save();

                $this->createPersonalOrganization($user);
            });
        } else {
            $user->save();
        }

        if ($user->wasRecentlyCreated) {
            // `plan` e os dois saldos de crédito vêm dos defaults do schema.
            $user->refresh();
        }

        return $user;
    }

    /**
     * Cria a organização "Personal" automática do primeiro login (SAD 20.3).
     *
     * Não é um tipo especial de organização — apenas uma organização comum,
     * sem plano (SAD 12.18), com o usuário como Owner e único membro
     * (ADR-010). O nome é convenção de apresentação, nunca um flag de
     * comportamento.
     *
     * A participação é escrita aqui, e não pelo `OrganizationMembershipService`,
     * porque este é o nascimento do Owner: o serviço recusa `owner` em qualquer
     * outro caminho que não a transferência de posse.
     */
    private function createPersonalOrganization(User $user): void
    {
        $organization = Organization::create([
            'name' => 'Personal',
            // Nunca apenas "personal": o slug é único e vários usuários
            // recebem a mesma organização "Personal" por nome.
            'slug' => 'personal-'.Str::lower(Str::random(8)),
            'plan' => null,
        ]);

        $organization->users()->attach($user->id, [
            'role' => OrganizationRole::Owner->value,
            'joined_at' => now(),
        ]);
    }
}
