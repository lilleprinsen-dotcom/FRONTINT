<?php

namespace App\Http\Controllers;

use App\Models\Connection;
use App\Services\Connections\ConnectionTester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConnectionTestController extends Controller
{
    public function __invoke(Request $request, Connection $connection, ConnectionTester $tester): JsonResponse|RedirectResponse
    {
        abort_unless(
            $request->user()->organizations()->whereKey($connection->organization_id)->exists(),
            403,
        );

        $result = $tester->test($connection);

        $connection->update([
            'status' => $result['status'],
            'last_checked_at' => now(),
        ]);

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        return back()->with('status', $result['message']);
    }
}
