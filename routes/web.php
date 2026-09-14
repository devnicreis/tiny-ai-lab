<?php

use App\Http\Controllers\Web\AppController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AppController::class, 'index'])->name('dashboard');

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

Route::middleware('auth.firebase')->group(function () {
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
| A identidade das telas Blade também vem do Firebase ID Token (SAD 5): não
| existe login por sessão no Tiny AI Lab, então as páginas usam exatamente o
| mesmo middleware das rotas de API.
|
*/

Route::middleware(['auth.firebase', 'organization'])->group(function () {
    Route::get('/briefing', [AppController::class, 'briefing'])->name('briefing');
    Route::get('/result/{execution}', [AppController::class, 'result'])->name('result');
});
