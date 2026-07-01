<?php

namespace App\Http\Controllers;

use App\Models\ConnectionDiscoverySnapshot;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\ProductSyncPreviewPlan;
use App\Models\ProductSyncProfile;
use App\Models\ProductSyncEvent;
use App\Models\ProductSyncRun;
use App\Jobs\RunLimitedFrontProductWriteTest;
use App\Services\ProductSync\ProductSyncPreviewRunBuilder;
use App\Services\ProductSync\ProductSyncProfileProvisioner;
use App\Services\ProductSync\DryRun\FrontProductWriteDryRunBuilder;
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
            'pendingIncrementalEvents' => $organization ? ProductSyncEvent::query()
                ->where('organization_id', $organization->id)
                ->whereIn('status', ['pending', 'queued'])
                ->count() : 0,
            'connectionStatuses' => $organization ? $this->connectionStatuses($organization) : [],
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
                ? ['preview_only', 'limited_write_test', 'initial_full_sync', 'incremental_sync', 'production']
                : ['preview_only', 'limited_write_test', 'initial_full_sync', 'incremental_sync'])],
            'sync_scope' => ['required', Rule::in(['all_active_products', 'selected_only', 'changed_since_last_sync', 'failed_only', 'category_filter', 'brand_filter'])],
            'max_products_per_run' => ['required', 'integer', 'min:1', 'max:50000'],
            'sync_only_opted_in_products' => ['nullable', 'boolean'],
            'include_simple_products' => ['nullable', 'boolean'],
            'include_variable_products' => ['nullable', 'boolean'],
            'include_variations' => ['nullable', 'boolean'],
            'include_draft_products' => ['nullable', 'boolean'],
            'include_private_products' => ['nullable', 'boolean'],
            'include_out_of_stock_products' => ['nullable', 'boolean'],
            'exclude_discontinued_products' => ['nullable', 'boolean'],
            'require_sku' => ['nullable', 'boolean'],
            'require_gtin' => ['nullable', 'boolean'],
            'require_price' => ['nullable', 'boolean'],
            'require_brand' => ['nullable', 'boolean'],
            'require_category' => ['nullable', 'boolean'],
            'max_products_per_batch' => ['required', 'integer', 'min:1', 'max:250'],
            'woo_page_size' => ['required', 'integer', 'min:10', 'max:100'],
            'front_page_size' => ['required', 'integer', 'min:10', 'max:100'],
            'max_runtime_seconds' => ['nullable', 'integer', 'min:30', 'max:3600'],
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'product_identity_strategy' => ['required', Rule::in(['woo_id_as_front_extid', 'sku_as_front_extid', 'gtin_as_primary'])],
            'gtin_field_strategy' => ['required', Rule::in(['auto_detect', 'configured_meta_key', 'zettle_barcode_fields'])],
            'configured_gtin_meta_key' => ['nullable', 'string', 'max:120'],
            'category_mapping_strategy' => ['nullable', 'string', 'max:120'],
            'brand_mapping_strategy' => ['nullable', 'string', 'max:120'],
            'price_strategy' => ['required', Rule::in(['regular_price_only', 'regular_price_now_sale_price_later', 'pricelist_v2_later'])],
            'stock_strategy' => ['required', Rule::in(['do_not_sync_stock_yet', 'preview_only', 'stock_sync_later'])],
            'incremental_sync_enabled' => ['nullable', 'boolean'],
            'webhook_updates_enabled' => ['nullable', 'boolean'],
            'reconciliation_enabled' => ['nullable', 'boolean'],
        ]);

        foreach ([
            'sync_only_opted_in_products',
            'include_simple_products',
            'include_variable_products',
            'include_variations',
            'include_draft_products',
            'include_private_products',
            'include_out_of_stock_products',
            'exclude_discontinued_products',
            'require_sku',
            'require_gtin',
            'require_price',
            'require_brand',
            'require_category',
            'incremental_sync_enabled',
            'webhook_updates_enabled',
            'reconciliation_enabled',
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

        $run->load(['profile', 'organization']);
        $itemsQuery = $run->items()->orderBy('id');

        if ($request->filled('sync_status')) {
            $itemsQuery->where('sync_status', (string) $request->string('sync_status'));
        }

        if ($request->filled('validation_status')) {
            $itemsQuery->where('validation_status', (string) $request->string('validation_status'));
        }

        if ($request->filled('q')) {
            $query = trim((string) $request->string('q'));
            $itemsQuery->where(function ($items) use ($query): void {
                $items->where('woo_sku', 'like', "%{$query}%")
                    ->orWhere('detected_gtin', 'like', "%{$query}%")
                    ->orWhere('front_product_id', 'like', "%{$query}%")
                    ->orWhere('front_product_ext_id', 'like', "%{$query}%")
                    ->orWhere('woo_product_id', $query);
            });
        }

        return view('product-sync.run', [
            'run' => $run,
            'items' => $itemsQuery->paginate(25)->withQueryString(),
            'productionWritesEnabled' => (bool) config('omnibridge.allow_production_writes'),
            'eligibleDryRunItems' => (clone $itemsQuery)
                ->whereIn('validation_status', ['ready', 'warning'])
                ->where('sync_status', 'not_started')
                ->limit(FrontProductWriteDryRunBuilder::MAX_ITEMS)
                ->get(),
        ]);
    }

    public function prepareFrontDryRun(
        Request $request,
        ProductSyncRun $run,
        FrontProductWriteDryRunBuilder $builder,
    ): RedirectResponse {
        abort_unless($request->user()->organizations()->whereKey($run->organization_id)->exists(), 403);

        $validated = $request->validate([
            'item_ids' => ['required', 'array', 'min:1', 'max:' . FrontProductWriteDryRunBuilder::MAX_ITEMS],
            'item_ids.*' => ['integer'],
        ]);

        $itemIds = array_map('intval', $validated['item_ids']);
        $dryRun = $builder->build($run, $itemIds);

        AuditLog::query()->create([
            'organization_id' => $run->organization_id,
            'user_id' => $request->user()->id,
            'action' => 'front_product_write_dry_run_prepared',
            'subject_type' => ProductSyncRun::class,
            'subject_id' => $run->id,
            'metadata_json' => [
                'status' => $dryRun['status'],
                'selected_count' => $dryRun['summary']['selected_count'],
                'item_ids' => $itemIds,
                'profile_mode' => $dryRun['summary']['profile_mode'],
                'production_writes_enabled' => $dryRun['summary']['production_writes_enabled'],
                'front_connection_id' => $dryRun['summary']['front_connection_id'],
                'external_api_calls' => false,
                'writes_performed' => false,
                'gate_errors' => $dryRun['gate_errors'],
            ],
        ]);

        if ($dryRun['status'] !== 'ready') {
            return redirect()
                ->route('product-sync.runs.show', $run)
                ->withErrors(['front_dry_run' => implode(' ', $dryRun['gate_errors'])]);
        }

        return redirect()
            ->route('product-sync.runs.front-dry-run.show', [
                'run' => $run,
                'items' => implode(',', $itemIds),
            ])
            ->with('status', 'Front write dry-run prepared. No external API calls were made.');
    }

    public function showFrontDryRun(
        Request $request,
        ProductSyncRun $run,
        FrontProductWriteDryRunBuilder $builder,
    ): View {
        abort_unless($request->user()->organizations()->whereKey($run->organization_id)->exists(), 403);

        $itemIds = collect(explode(',', (string) $request->query('items')))
            ->map(fn (string $id): int => (int) trim($id))
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();
        $dryRun = $builder->build($run, $itemIds);

        return view('product-sync.front-dry-run', [
            'run' => $run->load(['profile', 'organization']),
            'dryRun' => $dryRun,
            'productionWritesEnabled' => (bool) config('omnibridge.allow_production_writes'),
        ]);
    }

    public function runLimitedFrontWriteTest(Request $request, ProductSyncRun $run): RedirectResponse
    {
        abort_unless($request->user()->organizations()->whereKey($run->organization_id)->exists(), 403);

        $validated = $request->validate([
            'item_ids' => ['required', 'array', 'min:1', 'max:' . FrontProductWriteDryRunBuilder::MAX_ITEMS],
            'item_ids.*' => ['integer'],
        ]);

        RunLimitedFrontProductWriteTest::dispatch(
            $run->id,
            $request->user()->id,
            array_map('intval', $validated['item_ids']),
        );

        return redirect()
            ->route('product-sync.runs.show', $run)
            ->with('status', 'Limited Front write test started for selected items.');
    }

    public function runs(Request $request): View
    {
        $organizationIds = $request->user()->organizations()->pluck('organizations.id');
        $runs = ProductSyncRun::query()
            ->whereIn('organization_id', $organizationIds)
            ->with('organization')
            ->latest()
            ->paginate(20);

        return view('product-sync.runs', [
            'runs' => $runs,
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

    private function connectionStatuses(Organization $organization): array
    {
        $connections = $organization->connections()->get()->keyBy('type');

        return [
            'woocommerce' => $connections->get('woocommerce')?->status ?? 'Not connected',
            'front' => $connections->get('front_systems')?->status ?? $connections->get('front')?->status ?? 'Not connected',
        ];
    }

    private function runStats(?ProductSyncRun $run, ?ProductSyncPreviewPlan $plan): array
    {
        return [
            'selected' => $plan?->selected_count ?? 0,
            'candidates' => $run?->total_candidates ?? ($plan?->selected_count ?? 0),
            'ready' => $run?->total_ready ?? ($plan?->summary_json['ready_count'] ?? 0),
            'blocked' => $run?->total_blocked ?? ($plan?->summary_json['blocked_count'] ?? 0),
            'failed' => $run?->total_failed ?? 0,
            'pending' => $run?->total_pending ?? 0,
            'variations' => $run?->total_variations ?? 0,
            'last_successful_sync' => $run?->items()->whereNotNull('synced_at')->max('synced_at'),
        ];
    }
}
