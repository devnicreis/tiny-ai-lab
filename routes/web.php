<?php

use App\Http\Controllers\Web\AppController;
use App\Http\Controllers\Web\AuthController;
use Illuminate\Support\Facades\Route;

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
| O seletor reutiliza a lista do SwitchOrganizationModal (Sessão 3.2). O
| formulário de criação pertence ao fluxo de organizações e permanece pendente;
| a tela existe para que o redirecionamento e o link "+ Create new
| organization" tenham destino real.
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
| Shell autenticado
|--------------------------------------------------------------------------
|
| A identidade das telas Blade vem da sessão Laravel estabelecida no login
| (Sessão 3.1): navegação de browser não envia `Authorization: Bearer`, então
| estas rotas nunca usam `auth.firebase`, exclusivo das rotas de API.
|
| O dashboard entrou no grupo na Sessão 3.2: o shell (Sidebar e Header) mostra
| organização, Brand e créditos, e não existe sem identidade e contexto.
|
| `/settings/organizations` é o destino de "Manage all organizations" no
| seletor de organização. A gestão ainda não existe; a tela diz isso.
|
*/

Route::middleware(['auth.web', 'organization'])->group(function () {
    Route::get('/', [AppController::class, 'index'])->name('dashboard');

    Route::view('/settings/organizations', 'pages.organizations.settings')
        ->name('organizations.settings');

    Route::get('/briefing', [AppController::class, 'briefing'])->name('briefing');
    Route::get('/result/{execution}', [AppController::class, 'result'])->name('result');
});
