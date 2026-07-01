<?php

namespace App\Http\Controllers;

use App\Jobs\RunFrontSaleImport;
use App\Jobs\RunFrontSaleStockAdjustment;
use App\Models\FrontSaleImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FrontSaleImportController extends Controller
{
    public function index(Request $request): View
    {
        $organizationIds = $request->user()->organizations()->pluck('organizations.id');

        $imports = FrontSaleImport::query()
            ->whereIn('organization_id', $organizationIds)
            ->with(['organization', 'orderMapping'])
            ->latest()
            ->paginate(20);

        return view('sales.front-imports', [
            'imports' => $imports,
            'productionWritesEnabled' => (bool) config('omnibridge.allow_production_writes'),
        ]);
    }

    public function show(Request $request, FrontSaleImport $frontSaleImport): View
    {
        abort_unless($request->user()->organizations()->whereKey($frontSaleImport->organization_id)->exists(), 403);

        return view('sales.front-import', [
            'import' => $frontSaleImport->load(['organization', 'orderMapping']),
            'productionWritesEnabled' => (bool) config('omnibridge.allow_production_writes'),
        ]);
    }

    public function import(Request $request, FrontSaleImport $frontSaleImport): RedirectResponse
    {
        abort_unless($request->user()->organizations()->whereKey($frontSaleImport->organization_id)->exists(), 403);

        RunFrontSaleImport::dispatch($frontSaleImport->id, $request->user()->id);

        return redirect()
            ->route('front-sales.show', $frontSaleImport)
            ->with('status', 'Manual WooCommerce order import queued. Refresh this page or open Testing Log to see the result.');
    }

    public function adjustStock(Request $request, FrontSaleImport $frontSaleImport): RedirectResponse
    {
        abort_unless($request->user()->organizations()->whereKey($frontSaleImport->organization_id)->exists(), 403);

        RunFrontSaleStockAdjustment::dispatch($frontSaleImport->id, $request->user()->id);

        return redirect()
            ->route('front-sales.show', $frontSaleImport)
            ->with('status', 'WooCommerce stock adjustment queued for this Front transaction.');
    }
}
