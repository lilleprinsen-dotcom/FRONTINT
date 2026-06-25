<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        DB::connection()->getPdo();

        return response()->json([
            'status' => 'ok',
            'environment' => config('omnibridge.environment'),
            'production_writes_enabled' => config('omnibridge.allow_production_writes'),
        ]);
    }
}
