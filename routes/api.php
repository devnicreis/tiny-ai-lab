<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExecutionController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Autenticação
|--------------------------------------------------------------------------
|
| A identidade sempre vem do Firebase. O Laravel nunca emite JWT nem Access
| Token próprio: `/auth/firebase` apenas troca um Firebase ID Token válido
| pelo contexto local do usuário.
|
*/

Route::post('/auth/firebase', [AuthController::class, 'firebase']);

Route::middleware('auth.firebase')->group(function () {
    // `/auth/me` fica fora do middleware `organization`: é justamente a rota
    // que informa ao cliente quais organizações existem e qual está ativa —
    // exigir contexto aqui tornaria impossível descobri-lo (SAD 20.5).
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Também fora de `organization`: quem aceita um convite ainda não é
    // membro da organização de destino. A rota web `GET /invitations/{token}`
    // entra na Sessão 3.2, depois da ponte de autenticação web.
    Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept']);
});

/*
|--------------------------------------------------------------------------
| Domínio — exige contexto de organização
|--------------------------------------------------------------------------
|
| Toda rota daqui para baixo depende da organização ativa: é ela que define o
| conjunto de Marcas visíveis, o plano, o feature gating e as carteiras
| (SAD 20.5 e 20.6). O middleware roda sempre depois de `auth.firebase`.
|
*/

Route::middleware(['auth.firebase', 'organization'])->group(function () {
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    Route::match(['put', 'patch'], '/projects/{project}', [ProjectController::class, 'update']);
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);

    Route::get('/executions', [ExecutionController::class, 'index']);
    Route::post('/executions', [ExecutionController::class, 'store']);
    Route::get('/executions/{execution}', [ExecutionController::class, 'show']);
});

/*
|--------------------------------------------------------------------------
| Callback do n8n
|--------------------------------------------------------------------------
|
| Único caminho autorizado a finalizar uma Execution (SAD 7.7). Não usa
| `auth.firebase`: o n8n não é um usuário e nunca autentica ninguém. Sua
| segurança é exclusivamente o `X-N8N-Secret` comparado com `hash_equals()`
| pelo middleware `verify.n8n`.
|
| Esta rota nunca pode receber rate limiting por IP (CLAUDE.md §5).
|
*/

Route::middleware('verify.n8n')->post(
    '/webhook/n8n/result',
    [WebhookController::class, 'result'],
);
