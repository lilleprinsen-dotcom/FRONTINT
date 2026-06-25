<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GiftCardController extends Controller
{
    public function check(Request $request): JsonResponse
    {
        // TODO: Call signed WooCommerce plugin adapter endpoint.
        return response()->json(['status' => 'pending_implementation']);
    }

    public function redeem(Request $request): JsonResponse
    {
        // TODO: Use idempotency key and locking before WebToffee redemption.
        return response()->json(['status' => 'pending_implementation']);
    }

    public function reverse(Request $request): JsonResponse
    {
        // TODO: Reverse a previous redemption by source reference.
        return response()->json(['status' => 'pending_implementation']);
    }

    public function credit(Request $request): JsonResponse
    {
        // TODO: Credit gift card/store credit through adapter.
        return response()->json(['status' => 'pending_implementation']);
    }
}
