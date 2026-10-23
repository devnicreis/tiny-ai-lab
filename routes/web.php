<?php

use App\Http\Controllers\Web\AppController;
use App\Http\Controllers\Web\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AppController::class, 'index'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| Login web
|--------------------------------------------------------------------------
|
| Ponte entre o Firebase ID Token e a sessão Laravel convencional exigida
| pela navegação de browser (Sessão 3.1). Fora de `auth.web`: exigir sessão
| para acessar a tela que a estabelece seria um laço de redirecionamentos.
|
*/

Route::get('/login', [AuthController::class, 'create'])->name('login');
Route::post('/login/firebase', [AuthController::class, 'firebase'])->name('login.firebase');
Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

/*
|--------------------------------------------------------------------------
| Contexto de organização
|--------------------------------------------------------------------------
|
| Destinos do middleware `organization` quando não há contexto possível: sem
| nenhuma organização, criar; com mais de uma e nenhuma ativa, escolher. As duas
| telas ficam deliberadamente fora do middleware — exigir contexto delas seria
| um laço de redirecionamentos.
|
| A implementação das telas (formulário de criação, seletor, convites) pertence
| ao fluxo de organizações e permanece pendente; aqui elas existem para que o
| redirecionamento tenha destino real.
|
*/

Route::middleware('auth.web')->group(function () {
    Route::view('/organizations/create', 'pages.organizations.create')
        ->name('organizations.create');

    Route::view('/organizations/select', 'pages.organizations.select')
        ->name('organizations.select');
});

/*
|--------------------------------------------------------------------------
| Páginas do pipeline
|--------------------------------------------------------------------------
|
| A identidade das telas Blade vem da sessão Laravel estabelecida no login
| (Sessão 3.1): navegação de browser não envia `Authorization: Bearer`, então
| estas rotas nunca usam `auth.firebase`, exclusivo das rotas de API.
|
*/

Route::middleware(['auth.web', 'organization'])->group(function () {
    Route::get('/briefing', [AppController::class, 'briefing'])->name('briefing');
    Route::get('/result/{execution}', [AppController::class, 'result'])->name('result');
});
