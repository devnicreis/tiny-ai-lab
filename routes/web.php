<?php

use App\Http\Controllers\Web\AppController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AppController::class, 'index'])->name('dashboard');
