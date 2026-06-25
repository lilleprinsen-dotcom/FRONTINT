<?php

use App\Http\Controllers\Api\GiftCardController;
use App\Http\Controllers\Api\OrderResyncController;
use App\Http\Controllers\Api\StockReconciliationController;
use App\Http\Controllers\Api\ProductSyncController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Webhooks\FrontWebhookController;
use App\Http\Controllers\Webhooks\WooCommerceWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::post('/webhooks/woocommerce/{tenant}', WooCommerceWebhookController::class);
Route::post('/webhooks/front/{tenant}', FrontWebhookController::class);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/sync/products/run', [ProductSyncController::class, 'run']);
    Route::post('/sync/products/{id}', [ProductSyncController::class, 'syncProduct']);
    Route::post('/sync/stock/reconcile', StockReconciliationController::class);
    Route::post('/orders/{id}/resync', OrderResyncController::class);

    Route::post('/gift-cards/check', [GiftCardController::class, 'check']);
    Route::post('/gift-cards/redeem', [GiftCardController::class, 'redeem']);
    Route::post('/gift-cards/reverse', [GiftCardController::class, 'reverse']);
    Route::post('/gift-cards/credit', [GiftCardController::class, 'credit']);
});
