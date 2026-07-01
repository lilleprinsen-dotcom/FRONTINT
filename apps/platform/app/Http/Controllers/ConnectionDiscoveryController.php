<?php

namespace App\Http\Controllers;

use App\Models\Connection;
use App\Models\ConnectionDiscoverySnapshot;
use App\Services\Audit\ReadOnlyActionAuditor;
use App\Services\Discovery\ConnectionDiscoveryService;
use App\Services\Discovery\ProductMappingPreview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConnectionDiscoveryController extends Controller
{
    public function show(Request $request, Connection $connection, ProductMappingPreview $previewer): View
    {
        $this->authorizeConnection($request, $connection);
        $connection->loadMissing('organization');

        $snapshots = $connection->discoverySnapshots()
            ->latest()
            ->limit(10)
            ->get();

        return view('connections.discovery', [
            'connection' => $connection,
            'snapshots' => $snapshots,
            'latestStores' => $this->latestSnapshot($connection, 'stores'),
            'latestProducts' => $this->latestSnapshot($connection, 'products'),
            'latestFrontSetup' => $this->latestSnapshot($connection, 'front_setup'),
            'mappingPreview' => $this->mappingPreview($connection, $previewer),
            'connectionHttpTestsEnabled' => (bool) config('omnibridge.allow_connection_test_http'),
        ]);
    }

    public function discoverStores(
        Request $request,
        Connection $connection,
        ConnectionDiscoveryService $discovery,
        ReadOnlyActionAuditor $auditor,
    ): JsonResponse|RedirectResponse {
        $this->authorizeConnection($request, $connection);

        $snapshot = $discovery->discoverStores($connection);
        $this->auditIfLive($request, $connection, $snapshot, $auditor);

        return $this->respond($request, $snapshot);
    }

    public function discoverProducts(
        Request $request,
        Connection $connection,
        ConnectionDiscoveryService $discovery,
        ReadOnlyActionAuditor $auditor,
    ): JsonResponse|RedirectResponse {
        $this->authorizeConnection($request, $connection);

        $snapshot = $discovery->discoverProducts($connection);
        $this->auditIfLive($request, $connection, $snapshot, $auditor);

        return $this->respond($request, $snapshot);
    }

    public function discoverFrontSetup(
        Request $request,
        Connection $connection,
        ConnectionDiscoveryService $discovery,
        ReadOnlyActionAuditor $auditor,
    ): JsonResponse|RedirectResponse {
        $this->authorizeConnection($request, $connection);

        $snapshot = $discovery->discoverFrontSetup($connection);
        $this->auditIfLive($request, $connection, $snapshot, $auditor);

        return $this->respond($request, $snapshot);
    }

    private function authorizeConnection(Request $request, Connection $connection): void
    {
        abort_unless(
            $request->user()->organizations()->whereKey($connection->organization_id)->exists(),
            403,
        );
    }

    private function respond(Request $request, ConnectionDiscoverySnapshot $snapshot): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'status' => $snapshot->status,
                'discovery_type' => $snapshot->discovery_type,
                'summary' => $snapshot->summary_json,
                'sample' => $snapshot->sample_json,
                'error_message' => $snapshot->error_message,
                'checked_at' => $snapshot->checked_at?->toISOString(),
            ]);
        }

        return redirect()
            ->route('connections.discovery', $snapshot->connection_id)
            ->with('status', "Discovery {$snapshot->status}.");
    }

    private function auditIfLive(
        Request $request,
        Connection $connection,
        ConnectionDiscoverySnapshot $snapshot,
        ReadOnlyActionAuditor $auditor,
    ): void {
        if (! (bool) config('omnibridge.allow_connection_test_http')) {
            return;
        }

        $auditor->record(
            $request->user(),
            $connection,
            'live_readonly_discovery_' . $snapshot->discovery_type,
            $this->discoveryEndpointGroup($connection, $snapshot->discovery_type),
            $snapshot->status,
            $snapshot->checked_at,
        );
    }

    private function discoveryEndpointGroup(Connection $connection, string $type): string
    {
        if ($type === 'stores') {
            return 'GET /api/Stores';
        }

        if ($type === 'front_setup') {
            return 'GET /api/WebhooksTypes + GET /api/Stock/settings + GET /api/Stock/list';
        }

        return $connection->type === 'woocommerce'
            ? 'GET /wp-json/wc/v3/products'
            : 'POST /api/Product';
    }

    private function latestSnapshot(Connection $connection, string $type): ?ConnectionDiscoverySnapshot
    {
        return $connection->discoverySnapshots()
            ->where('discovery_type', $type)
            ->latest()
            ->first();
    }

    private function mappingPreview(Connection $connection, ProductMappingPreview $previewer): array
    {
        $organizationId = $connection->organization_id;
        $wooSnapshot = ConnectionDiscoverySnapshot::query()
            ->where('organization_id', $organizationId)
            ->where('source_system', 'woocommerce')
            ->where('discovery_type', 'products')
            ->where('status', 'success')
            ->latest()
            ->first();

        $frontSnapshot = ConnectionDiscoverySnapshot::query()
            ->where('organization_id', $organizationId)
            ->whereIn('source_system', ['front', 'front_systems'])
            ->where('discovery_type', 'products')
            ->where('status', 'success')
            ->latest()
            ->first();

        $wooSample = $wooSnapshot?->sample_json ?? [];
        $frontSample = $frontSnapshot?->sample_json ?? [];
        $wooProducts = is_array($wooSample) ? ($wooSample['products'] ?? []) : [];
        $frontProducts = is_array($frontSample) ? ($frontSample['products'] ?? []) : [];

        if (! is_array($wooProducts) || ! is_array($frontProducts) || $wooProducts === [] || $frontProducts === []) {
            return [];
        }

        return $previewer->preview($wooProducts, $frontProducts);
    }
}
