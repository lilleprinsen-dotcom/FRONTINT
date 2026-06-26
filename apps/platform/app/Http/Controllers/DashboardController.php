<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $organizations = $request->user()
            ->organizations()
            ->with([
                'connections.credentials',
                'webhookEndpoints',
            ])
            ->orderBy('name')
            ->get();

        $organizationIds = $organizations->pluck('id');

        return view('dashboard', [
            'organizations' => $organizations,
            'failedEventsCount' => Event::query()
                ->whereIn('organization_id', $organizationIds)
                ->where('status', 'failed')
                ->count(),
            'recentEvents' => Event::query()
                ->whereIn('organization_id', $organizationIds)
                ->latest()
                ->limit(10)
                ->get(),
            'connectionTypes' => config('omnibridge.connection_types'),
            'environment' => config('omnibridge.environment'),
            'productionWritesEnabled' => (bool) config('omnibridge.allow_production_writes'),
            'connectionHttpTestsEnabled' => (bool) config('omnibridge.allow_connection_test_http'),
        ]);
    }
}
