<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AdvancedController;
use App\Http\Controllers\ConnectionController;
use App\Http\Controllers\ConnectionDiscoveryController;
use App\Http\Controllers\ConnectionTestController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscoveryIndexController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ProductMappingPocController;
use App\Http\Controllers\ProductSyncController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/connections', fn () => redirect()->route('dashboard'))->name('connections.index');
    Route::get('/discovery', DiscoveryIndexController::class)->name('discovery.index');
    Route::get('/advanced', AdvancedController::class)->name('advanced.index');

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

    Route::get('/product-sync', [ProductSyncController::class, 'index'])
        ->name('product-sync.index');
    Route::post('/product-sync/preview-run', [ProductSyncController::class, 'createPreviewRun'])
        ->name('product-sync.preview-run');
    Route::get('/product-sync/profile', [ProductSyncController::class, 'profile'])
        ->name('product-sync.profile');
    Route::post('/product-sync/profile', [ProductSyncController::class, 'updateProfile'])
        ->name('product-sync.profile.update');
    Route::get('/product-sync/runs', [ProductSyncController::class, 'runs'])
        ->name('product-sync.runs.index');
    Route::get('/product-sync/runs/{run}', [ProductSyncController::class, 'showRun'])
        ->name('product-sync.runs.show');
});
