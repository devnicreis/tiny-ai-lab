<?php

use App\Http\Controllers\Api\AuthController;
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
    Route::get('/auth/me', [AuthController::class, 'me']);
});
