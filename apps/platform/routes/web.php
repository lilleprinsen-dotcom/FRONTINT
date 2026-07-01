<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AdvancedController;
use App\Http\Controllers\ConnectionController;
use App\Http\Controllers\ConnectionDiscoveryController;
use App\Http\Controllers\ConnectionTestController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscoveryIndexController;
use App\Http\Controllers\LabController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ProductMappingPocController;
use App\Http\Controllers\ProductSyncController;
use App\Http\Controllers\TestingLogController;
use App\Http\Controllers\WooReadinessController;
use App\Http\Controllers\WooCommercePluginAdapterTestController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/lab', LabController::class)->name('lab.index');
    Route::get('/discovery', DiscoveryIndexController::class)->name('discovery.index');
    Route::get('/woo-readiness', WooReadinessController::class)->name('woo-readiness.index');
    Route::get('/testing-log', TestingLogController::class)->name('testing-log.index');
    Route::get('/advanced', AdvancedController::class)->name('advanced.index');

    Route::resource('organizations', OrganizationController::class)
        ->only(['index', 'create', 'store', 'edit', 'update']);

    Route::resource('connections', ConnectionController::class)
        ->only(['index', 'create', 'store', 'edit', 'update']);

    Route::post('/connections/{connection}/test', ConnectionTestController::class)
        ->name('connections.test');
    Route::post('/connections/{connection}/test-woocommerce-plugin', WooCommercePluginAdapterTestController::class)
        ->name('connections.test-woocommerce-plugin');

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
    Route::post('/product-sync/staging-batch-run', [ProductSyncController::class, 'createStagingBatchRun'])
        ->name('product-sync.staging-batch-run');
    Route::get('/product-sync/profile', [ProductSyncController::class, 'profile'])
        ->name('product-sync.profile');
    Route::post('/product-sync/profile', [ProductSyncController::class, 'updateProfile'])
        ->name('product-sync.profile.update');
    Route::get('/product-sync/runs', [ProductSyncController::class, 'runs'])
        ->name('product-sync.runs.index');
    Route::get('/product-sync/runs/{run}', [ProductSyncController::class, 'showRun'])
        ->name('product-sync.runs.show');
    Route::post('/product-sync/runs/{run}/front-dry-run', [ProductSyncController::class, 'prepareFrontDryRun'])
        ->name('product-sync.runs.front-dry-run.prepare');
    Route::get('/product-sync/runs/{run}/front-dry-run', [ProductSyncController::class, 'showFrontDryRun'])
        ->name('product-sync.runs.front-dry-run.show');
    Route::post('/product-sync/runs/{run}/limited-front-write-test', [ProductSyncController::class, 'runLimitedFrontWriteTest'])
        ->name('product-sync.runs.limited-front-write-test');
    Route::post('/product-sync/runs/{run}/staging-batch-sync', [ProductSyncController::class, 'runStagingBatchSync'])
        ->name('product-sync.runs.staging-batch-sync');
    Route::post('/product-sync/runs/{run}/retry-failed', [ProductSyncController::class, 'retryFailedItems'])
        ->name('product-sync.runs.retry-failed');
    Route::post('/product-sync/runs/{run}/sale-prices', [ProductSyncController::class, 'runSalePriceSync'])
        ->name('product-sync.runs.sale-prices');
    Route::post('/product-sync/runs/{run}/retry-sale-prices', [ProductSyncController::class, 'retrySalePrices'])
        ->name('product-sync.runs.retry-sale-prices');
    Route::post('/product-sync/runs/{run}/stock', [ProductSyncController::class, 'runStockSync'])
        ->name('product-sync.runs.stock');
    Route::post('/product-sync/runs/{run}/retry-stock', [ProductSyncController::class, 'retryStock'])
        ->name('product-sync.runs.retry-stock');
});
