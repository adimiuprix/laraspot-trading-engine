<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ExchangeController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/', function () {
        return inertia('Home');
    })->name('home');

    Route::get('/login', function () {
        return redirect()->route('home');
    })->name('login');

    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('/exchange', [ExchangeController::class, 'index'])->name('exchange');

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
