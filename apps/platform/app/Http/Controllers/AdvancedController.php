<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdvancedController extends Controller
{
    public function __invoke(Request $request): View
    {
        $organizations = $request->user()
            ->organizations()
            ->with(['connections.credentials', 'webhookEndpoints', 'productSyncProfiles'])
            ->orderBy('name')
            ->get();

        $organizationIds = $organizations->pluck('id');

        return view('advanced.index', [
            'organizations' => $organizations,
            'recentEvents' => Event::query()
                ->whereIn('organization_id', $organizationIds)
                ->latest()
                ->limit(20)
                ->get(),
            'recentAuditLogs' => AuditLog::query()
                ->whereIn('organization_id', $organizationIds)
                ->latest()
                ->limit(20)
                ->get(),
            'productionWritesEnabled' => (bool) config('omnibridge.allow_production_writes'),
            'connectionHttpTestsEnabled' => (bool) config('omnibridge.allow_connection_test_http'),
        ]);
    }
}
