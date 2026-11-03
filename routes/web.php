<?php

use App\Http\Controllers\Auth\RegisterController;

Route::get('/', [RegisterController::class, 'create']);
Route::get('/register', [RegisterController::class, 'create'])->name('register');
Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
