<?php

use App\Models\Execution;
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
 * Escuta quem é membro da organização dona da Brand da Execution — o mesmo
 * critério da ExecutionPolicy, nunca `executions.user_id` (SAD 20.6): a Brand é
 * da organização e o trabalho produzido nela também. Os eventos transportam
 * apenas metadados de estado — nunca `executions.result` (SAD 9.6).
 */
Broadcast::channel('execution.{executionId}', function (User $user, string $executionId): bool {
    return Execution::query()->whereKey($executionId)->visibleTo($user)->exists();
});
