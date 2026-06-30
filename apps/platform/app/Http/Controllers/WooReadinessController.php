<?php

namespace App\Http\Controllers;

use App\Models\ConnectionDiscoverySnapshot;
use App\Services\Readiness\WooProductReadinessSummary;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WooReadinessController extends Controller
{
    public function __invoke(Request $request, WooProductReadinessSummary $readiness): View
    {
        $organizationIds = $request->user()->organizations()->pluck('organizations.id');
        $snapshot = ConnectionDiscoverySnapshot::query()
            ->whereIn('organization_id', $organizationIds)
            ->where('source_system', 'woocommerce')
            ->where('discovery_type', 'products')
            ->where('status', 'success')
            ->with(['organization', 'connection'])
            ->latest('checked_at')
            ->first();

        return view('readiness.woo', [
            'summary' => $readiness->summarize($snapshot),
            'snapshot' => $snapshot,
        ]);
    }
}
