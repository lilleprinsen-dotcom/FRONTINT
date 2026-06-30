<?php

namespace App\Http\Controllers;

use App\Models\Connection;
use App\Services\Audit\ReadOnlyActionAuditor;
use App\Services\WooCommerce\WooCommercePluginAdapterClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WooCommercePluginAdapterTestController extends Controller
{
    public function __invoke(
        Request $request,
        Connection $connection,
        WooCommercePluginAdapterClient $client,
        ReadOnlyActionAuditor $auditor,
    ): JsonResponse|RedirectResponse {
        abort_unless(
            $request->user()->organizations()->whereKey($connection->organization_id)->exists(),
            403,
        );

        abort_unless($connection->type === 'woocommerce', 404);

        $result = $client->test($connection);
        $checkedAt = now();

        $connection->update([
            'status' => $result['status'],
            'last_checked_at' => $checkedAt,
            'last_test_status' => 'plugin_adapter_' . $result['status'],
            'last_http_status' => $result['http_status'] ?? null,
            'last_response_time_ms' => $result['response_time_ms'] ?? null,
            'last_error' => $result['last_error'] ?? null,
            'last_test_metadata' => [
                'plugin_adapter' => $result['metadata'] ?? null,
            ],
        ]);

        $result['checked_at'] = $checkedAt->toISOString();

        if ($result['http_checked'] ?? false) {
            $auditor->record(
                $request->user(),
                $connection,
                'readonly_woocommerce_plugin_adapter_test',
                'GET /wp-json/omnibridge/v1/connection-test',
                $result['status'],
                $checkedAt,
            );
        }

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        return back()->with('status', $result['message']);
    }
}
