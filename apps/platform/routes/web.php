<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ConnectionController;
use App\Http\Controllers\ConnectionDiscoveryController;
use App\Http\Controllers\ConnectionTestController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ProductMappingPocController;
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

    Route::get('/connections/{connection}/discovery', [ConnectionDiscoveryController::class, 'show'])
        ->name('connections.discovery');
    Route::post('/connections/{connection}/discover/stores', [ConnectionDiscoveryController::class, 'discoverStores'])
        ->name('connections.discover.stores');
    Route::post('/connections/{connection}/discover/products', [ConnectionDiscoveryController::class, 'discoverProducts'])
        ->name('connections.discover.products');

    Route::get('/mapping/product-poc', [ProductMappingPocController::class, 'show'])
        ->name('mapping.product-poc');
    Route::post('/mapping/product-poc/plan', [ProductMappingPocController::class, 'plan'])
        ->name('mapping.product-poc.plan');
});
