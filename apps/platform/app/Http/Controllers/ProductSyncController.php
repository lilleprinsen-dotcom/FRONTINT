<?php

namespace App\Http\Controllers;

use App\Models\ConnectionDiscoverySnapshot;
use App\Models\Organization;
use App\Models\ProductSyncPreviewPlan;
use App\Models\ProductSyncProfile;
use App\Models\ProductSyncRun;
use App\Services\ProductSync\ProductSyncPreviewRunBuilder;
use App\Services\ProductSync\ProductSyncProfileProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductSyncController extends Controller
{
    public function index(Request $request, ProductSyncProfileProvisioner $profiles): View
    {
        $organization = $this->currentOrganization($request);
        $profile = $organization ? $profiles->ensureDefault($organization) : null;
        $latestRun = $organization ? $this->latestRun($organization) : null;
        $latestPlan = $organization ? $this->latestPreviewPlan($organization) : null;

        return view('product-sync.index', [
            'organization' => $organization,
            'profile' => $profile,
            'latestRun' => $latestRun,
            'latestPlan' => $latestPlan,
            'lastDiscovery' => $organization ? $this->lastDiscovery($organization) : null,
            'productionWritesEnabled' => (bool) config('omnibridge.allow_production_writes'),
            'stats' => $this->runStats($latestRun, $latestPlan),
        ]);
    }

    public function profile(Request $request, ProductSyncProfileProvisioner $profiles): View
    {
        $organization = $this->currentOrganization($request);

        return view('product-sync.profile', [
            'organization' => $organization,
            'profile' => $organization ? $profiles->ensureDefault($organization) : null,
            'productionWritesEnabled' => (bool) config('omnibridge.allow_production_writes'),
        ]);
    }

    public function updateProfile(
        Request $request,
        ProductSyncProfileProvisioner $profiles,
    ): RedirectResponse {
        $organization = $this->currentOrganization($request);

        abort_unless($organization, 404);

        $profile = $profiles->ensureDefault($organization);
        $productionWritesEnabled = (bool) config('omnibridge.allow_production_writes');

        $validated = $request->validate([
            'mode' => ['required', Rule::in($productionWritesEnabled
                ? ['preview_only', 'limited_write_test', 'production']
                : ['preview_only', 'limited_write_test'])],
            'max_products_per_run' => ['required', 'integer', 'min:1', 'max:1000'],
            'sync_only_opted_in_products' => ['nullable', 'boolean'],
            'include_simple_products' => ['nullable', 'boolean'],
            'include_variable_products' => ['nullable', 'boolean'],
            'include_variations' => ['nullable', 'boolean'],
            'require_sku' => ['nullable', 'boolean'],
            'require_gtin' => ['nullable', 'boolean'],
            'require_price' => ['nullable', 'boolean'],
            'require_brand' => ['nullable', 'boolean'],
            'require_category' => ['nullable', 'boolean'],
            'max_products_per_batch' => ['required', 'integer', 'min:1', 'max:250'],
            'woo_query_limit' => ['required', 'integer', 'min:10', 'max:250'],
            'front_write_limit' => ['required', 'integer', 'min:1', 'max:100'],
            'default_front_group_strategy' => ['nullable', 'string', 'max:120'],
            'default_front_subgroup_strategy' => ['nullable', 'string', 'max:120'],
            'default_front_brand_strategy' => ['nullable', 'string', 'max:120'],
            'price_strategy' => ['required', Rule::in(['regular_price_only', 'regular_and_sale_preview', 'pricelist_v2_later'])],
            'stock_strategy' => ['required', Rule::in(['do_not_sync_stock_yet', 'preview_only'])],
        ]);

        foreach ([
            'sync_only_opted_in_products',
            'include_simple_products',
            'include_variable_products',
            'include_variations',
            'require_sku',
            'require_gtin',
            'require_price',
            'require_brand',
            'require_category',
        ] as $checkbox) {
            $validated[$checkbox] = (bool) ($validated[$checkbox] ?? false);
        }

        $profile->update($validated);

        return redirect()
            ->route('product-sync.profile')
            ->with('status', 'Product sync profile saved.');
    }

    public function createPreviewRun(
        Request $request,
        ProductSyncProfileProvisioner $profiles,
        ProductSyncPreviewRunBuilder $builder,
    ): RedirectResponse {
        $organization = $this->currentOrganization($request);

        abort_unless($organization, 404);

        $profile = $profiles->ensureDefault($organization);
        $latestPlan = $this->latestPreviewPlan($organization);

        if (! $latestPlan) {
            return redirect()
                ->route('mapping.product-poc')
                ->withErrors(['product_sync' => 'Generate a 10-product mapping PoC plan before creating a preview run.']);
        }

        $run = $builder->createFromPreviewPlan($request->user(), $profile, $latestPlan);

        return redirect()
            ->route('product-sync.runs.show', $run)
            ->with('status', 'Preview run created. No products were synced.');
    }

    public function showRun(Request $request, ProductSyncRun $run): View
    {
        abort_unless($request->user()->organizations()->whereKey($run->organization_id)->exists(), 403);

        $run->load(['items', 'profile', 'organization']);

        return view('product-sync.run', [
            'run' => $run,
            'productionWritesEnabled' => (bool) config('omnibridge.allow_production_writes'),
        ]);
    }

    private function currentOrganization(Request $request): ?Organization
    {
        return $request->user()
            ->organizations()
            ->with(['productSyncProfiles'])
            ->orderBy('name')
            ->first();
    }

    private function latestPreviewPlan(Organization $organization): ?ProductSyncPreviewPlan
    {
        return ProductSyncPreviewPlan::query()
            ->where('organization_id', $organization->id)
            ->latest()
            ->first();
    }

    private function latestRun(Organization $organization): ?ProductSyncRun
    {
        return ProductSyncRun::query()
            ->where('organization_id', $organization->id)
            ->latest()
            ->first();
    }

    private function lastDiscovery(Organization $organization): ?ConnectionDiscoverySnapshot
    {
        return ConnectionDiscoverySnapshot::query()
            ->where('organization_id', $organization->id)
            ->where('discovery_type', 'products')
            ->latest('checked_at')
            ->first();
    }

    private function runStats(?ProductSyncRun $run, ?ProductSyncPreviewPlan $plan): array
    {
        return [
            'selected' => $plan?->selected_count ?? 0,
            'ready' => $run?->total_ready ?? ($plan?->summary_json['ready_count'] ?? 0),
            'blocked' => $run?->total_blocked ?? ($plan?->summary_json['blocked_count'] ?? 0),
            'failed' => $run?->total_failed ?? 0,
            'last_successful_sync' => $run?->items?->whereNotNull('synced_at')->max('synced_at'),
        ];
    }
}
