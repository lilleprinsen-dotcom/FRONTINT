<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class StockReconciliationController extends Controller
{
    public function __invoke(): JsonResponse
    {
        // TODO: Queue stock reconciliation using WooCommerce as master.
        return response()->json(['status' => 'queued']);
    }
}
