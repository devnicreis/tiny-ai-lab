<?php

use App\Http\Controllers\Web\AppController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AppController::class, 'index'])->name('dashboard');

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

Route::middleware('auth.firebase')->group(function () {
    Route::get('/briefing', [AppController::class, 'briefing'])->name('briefing');
    Route::get('/result/{execution}', [AppController::class, 'result'])->name('result');
});
