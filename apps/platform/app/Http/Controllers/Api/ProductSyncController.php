<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ProductSyncController extends Controller
{
    public function run(): JsonResponse
    {
        // TODO: Create tenant-aware sync_run and queue paginated WooCommerce product sync.
        return response()->json(['status' => 'queued']);
    }

    public function syncProduct(string $id): JsonResponse
    {
        // TODO: Queue a single-product sync after product validation.
        return response()->json(['status' => 'queued', 'product_id' => $id]);
    }
}
