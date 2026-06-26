<?php

namespace App\Http\Controllers;

use App\Models\Connection;
use App\Services\Audit\ReadOnlyActionAuditor;
use App\Services\Connections\ConnectionTester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConnectionTestController extends Controller
{
    public function __invoke(
        Request $request,
        Connection $connection,
        ConnectionTester $tester,
        ReadOnlyActionAuditor $auditor,
    ): JsonResponse|RedirectResponse
    {
        abort_unless(
            $request->user()->organizations()->whereKey($connection->organization_id)->exists(),
            403,
        );

        $result = $tester->test($connection);
        $checkedAt = now();

        $connection->update([
            'status' => $result['status'],
            'last_checked_at' => $checkedAt,
            'last_test_status' => $result['status'],
            'last_http_status' => $result['http_status'] ?? null,
            'last_response_time_ms' => $result['response_time_ms'] ?? null,
            'last_error' => $result['last_error'] ?? null,
            'last_test_metadata' => $result['metadata'] ?? null,
        ]);

        $result['checked_at'] = $checkedAt->toISOString();

        if ((bool) config('omnibridge.allow_connection_test_http')) {
            $auditor->record(
                $request->user(),
                $connection,
                'live_readonly_connection_test',
                $result['operation'] ?? $this->connectionTestEndpointGroup($connection),
                $result['status'],
                $checkedAt,
            );
        }

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        return back()->with('status', $result['message']);
    }

    private function connectionTestEndpointGroup(Connection $connection): string
    {
        return match ($connection->type) {
            'woocommerce' => 'GET /wp-json/wc/v3/system_status',
            'front', 'front_systems' => 'GET /api/Environment',
            default => 'connection test',
        };
    }
}
