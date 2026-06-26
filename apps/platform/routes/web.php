<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ConnectionController;
use App\Http\Controllers\ConnectionTestController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::resource('organizations', OrganizationController::class)
        ->only(['index', 'create', 'store', 'edit', 'update']);

    Route::resource('connections', ConnectionController::class)
        ->only(['create', 'store', 'edit', 'update']);

    Route::post('/connections/{connection}/test', ConnectionTestController::class)
        ->name('connections.test');
});
