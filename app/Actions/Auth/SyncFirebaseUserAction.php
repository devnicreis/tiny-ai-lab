<?php

namespace App\Actions\Auth;

use App\Models\User;
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

        $user->email = $email;
        $user->name = is_string($name) && $name !== '' ? $name : Str::before($email, '@');

        if (($claims['email_verified'] ?? false) && $user->email_verified_at === null) {
            $user->email_verified_at = now();
        }

        $user->save();

        if ($user->wasRecentlyCreated) {
            // `plan` e os dois saldos de crédito vêm dos defaults do schema.
            $user->refresh();
        }

        return $user;
    }
}
