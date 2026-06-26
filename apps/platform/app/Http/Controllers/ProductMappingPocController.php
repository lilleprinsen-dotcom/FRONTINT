<?php

namespace App\Http\Controllers;

use App\Models\ConnectionDiscoverySnapshot;
use App\Models\ProductSyncPreviewPlan;
use App\Services\Mapping\ProductSyncPreviewPlanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ProductMappingPocController extends Controller
{
    public function show(Request $request, ProductSyncPreviewPlanner $planner): View
    {
        [$wooSnapshot, $frontSnapshot] = $this->latestSnapshotPair($request->user()->organizations()->pluck('organizations.id'));
        $latestPlan = $this->latestPlan($request, $wooSnapshot?->organization_id);

        $wooProducts = $this->productsFromSnapshot($wooSnapshot);
        $frontProducts = $this->productsFromSnapshot($frontSnapshot);

        return view('mapping.product-poc', [
            'wooSnapshot' => $wooSnapshot,
            'frontSnapshot' => $frontSnapshot,
            'latestPlan' => $latestPlan,
            'wooProducts' => $wooProducts,
            'frontProducts' => $frontProducts,
            'previewRows' => $wooSnapshot && $frontSnapshot ? $planner->previewRows($wooProducts, $frontProducts) : [],
            'productionWritesEnabled' => (bool) config('omnibridge.allow_production_writes'),
        ]);
    }

    public function plan(Request $request, ProductSyncPreviewPlanner $planner): RedirectResponse
    {
        $validated = $request->validate([
            'woo_product_ids' => ['required', 'array', 'min:1', 'max:' . ProductSyncPreviewPlanner::MAX_SELECTED_PRODUCTS],
            'woo_product_ids.*' => ['required'],
        ], [
            'woo_product_ids.max' => 'Select no more than 10 WooCommerce products for this PoC plan.',
        ]);

        [$wooSnapshot, $frontSnapshot] = $this->latestSnapshotPair($request->user()->organizations()->pluck('organizations.id'));

        if (! $wooSnapshot) {
            return back()->withErrors(['woo_product_ids' => 'Run WooCommerce product discovery before generating a mapping PoC plan.']);
        }

        if (! $frontSnapshot) {
            return back()->withErrors(['woo_product_ids' => 'Run Front product discovery before generating a mapping PoC plan.']);
        }

        $availableIds = collect($wooSnapshot->sample_json['products'] ?? [])
            ->filter(fn ($product): bool => is_array($product))
            ->map(fn (array $product): string => (string) ($product['id'] ?? ''))
            ->filter()
            ->values();

        $selectedIds = collect($validated['woo_product_ids'])->map(fn (mixed $id): string => (string) $id)->values();

        if ($selectedIds->diff($availableIds)->isNotEmpty()) {
            return back()->withErrors(['woo_product_ids' => 'One or more selected products were not found in the latest WooCommerce discovery snapshot.']);
        }

        $plan = $planner->createPlan($request->user(), $wooSnapshot, $frontSnapshot, $selectedIds->all());

        return redirect()
            ->route('mapping.product-poc')
            ->with('status', "Preview sync plan {$plan->status}: {$plan->selected_count} product(s) selected.");
    }

    private function latestSnapshotPair(Collection $organizationIds): array
    {
        $wooSnapshots = ConnectionDiscoverySnapshot::query()
            ->whereIn('organization_id', $organizationIds)
            ->where('source_system', 'woocommerce')
            ->where('discovery_type', 'products')
            ->where('status', 'success')
            ->latest()
            ->limit(10)
            ->get();

        foreach ($wooSnapshots as $wooSnapshot) {
            $frontSnapshot = $this->latestFrontSnapshot($wooSnapshot->organization_id);

            if ($frontSnapshot) {
                return [$wooSnapshot, $frontSnapshot];
            }
        }

        $wooSnapshot = $wooSnapshots->first();

        return [$wooSnapshot, $wooSnapshot ? $this->latestFrontSnapshot($wooSnapshot->organization_id) : null];
    }

    private function latestFrontSnapshot(int $organizationId): ?ConnectionDiscoverySnapshot
    {
        return ConnectionDiscoverySnapshot::query()
            ->where('organization_id', $organizationId)
            ->whereIn('source_system', ['front', 'front_systems'])
            ->where('discovery_type', 'products')
            ->where('status', 'success')
            ->latest()
            ->first();
    }

    private function latestPlan(Request $request, ?int $organizationId): ?ProductSyncPreviewPlan
    {
        $query = ProductSyncPreviewPlan::query()
            ->whereIn('organization_id', $request->user()->organizations()->pluck('organizations.id'))
            ->latest();

        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        return $query->first();
    }

    private function productsFromSnapshot(?ConnectionDiscoverySnapshot $snapshot): array
    {
        $products = $snapshot?->sample_json['products'] ?? [];

        return is_array($products) ? $products : [];
    }
}
