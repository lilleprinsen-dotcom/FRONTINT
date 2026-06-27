<?php

namespace App\Http\Controllers;

use App\Models\ConnectionDiscoverySnapshot;
use App\Models\ProductSyncPreviewPlan;
use App\Models\ProductSyncRun;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LabController extends Controller
{
    public function __invoke(Request $request): View
    {
        $organizations = $request->user()
            ->organizations()
            ->with(['connections.latestDiscoverySnapshot'])
            ->orderBy('name')
            ->get();
        $organizationIds = $organizations->pluck('id');

        return view('lab.index', [
            'organizations' => $organizations,
            'latestDiscovery' => ConnectionDiscoverySnapshot::query()
                ->whereIn('organization_id', $organizationIds)
                ->latest('checked_at')
                ->first(),
            'latestPlan' => ProductSyncPreviewPlan::query()
                ->whereIn('organization_id', $organizationIds)
                ->latest()
                ->first(),
            'latestRun' => ProductSyncRun::query()
                ->whereIn('organization_id', $organizationIds)
                ->latest()
                ->first(),
            'productionWritesEnabled' => (bool) config('omnibridge.allow_production_writes'),
            'connectionHttpTestsEnabled' => (bool) config('omnibridge.allow_connection_test_http'),
        ]);
    }
}
