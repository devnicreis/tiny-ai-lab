<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Canais de Broadcast
|--------------------------------------------------------------------------
|
| Todo canal do Tiny AI Lab é privado (SAD 9.5). A autorização pertence
| exclusivamente ao Laravel; o Reverb apenas distribui mensagens já
| autorizadas aqui.
|
*/

/**
 * Canal privado do próprio usuário — utilizado pelas notificações broadcast.
 */
Broadcast::channel('App.Models.User.{id}', function (User $user, string $id): bool {
    return (int) $user->id === (int) $id;
});

/**
 * Canal privado de uma Execution.
 *
 * Somente o dono da Execution escuta o canal. Os eventos transportam apenas
 * metadados de estado — nunca `executions.result` (SAD 9.6).
 */
Broadcast::channel('execution.{executionId}', function (User $user, string $executionId): bool {
    return $user->executions()->whereKey($executionId)->exists();
});
