<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return $this->ready();
    }

    public function live(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'check' => 'live',
            'environment' => config('omnibridge.environment'),
            'production_writes_enabled' => config('omnibridge.allow_production_writes'),
        ]);
    }

    public function ready(): JsonResponse
    {
        DB::connection()->getPdo();

        return response()->json([
            'status' => 'ok',
            'check' => 'ready',
            'database' => 'ok',
            'environment' => config('omnibridge.environment'),
            'production_writes_enabled' => config('omnibridge.allow_production_writes'),
        ]);
    }
}
