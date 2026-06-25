<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class OrderResyncController extends Controller
{
    public function __invoke(string $id): JsonResponse
    {
        // TODO: Queue order resync by Woo or Front mapping reference.
        return response()->json(['status' => 'queued', 'order_id' => $id]);
    }
}
