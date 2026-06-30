<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ProductMapping;
use App\Models\ProductSyncEvent;
use App\Models\ProductSyncPreviewPlan;
use App\Models\ProductSyncProfile;
use App\Models\ProductSyncRun;
use App\Models\ProductSyncRunItem;
use App\Models\User;
use App\Services\ProductSync\ProductSyncEventRecorder;
use App\Services\ProductSync\ProductSyncProfileProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductSyncFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_sync_page_requires_authentication(): void
    {
        $this->get('/product-sync')
            ->assertRedirect('/login');
    }

    public function test_default_sync_profile_is_created_for_organization(): void
    {
        [$user] = $this->userWithOrganization();

        $this->actingAs($user)
            ->get('/product-sync')
            ->assertOk()
            ->assertSee('Preview only');

        $this->assertDatabaseHas('product_sync_profiles', [
            'name' => 'Default safe product sync profile',
            'mode' => 'preview_only',
            'max_products_per_batch' => 25,
            'max_products_per_run' => 100,
            'sync_scope' => 'selected_only',
            'sync_only_opted_in_products' => true,
            'include_variable_products' => true,
            'include_variations' => true,
            'require_gtin' => false,
            'incremental_sync_enabled' => false,
            'webhook_updates_enabled' => false,
            'reconciliation_enabled' => false,
        ]);
    }

    public function test_untouched_default_sync_profile_is_migrated_to_sku_fallback_gtin_policy(): void
    {
        [, $organization] = $this->userWithOrganization();
        $profile = ProductSyncProfile::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Default safe product sync profile',
            'is_active' => true,
            'mode' => 'preview_only',
            'sync_scope' => 'selected_only',
            'require_gtin' => true,
        ]);

        $timestamp = now()->subMinute();
        ProductSyncProfile::query()->whereKey($profile->id)->update([
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $ensured = app(ProductSyncProfileProvisioner::class)->ensureDefault($organization);

        $this->assertFalse($ensured->require_gtin);
    }

    public function test_production_mode_cannot_be_enabled_when_production_writes_disabled(): void
    {
        config(['omnibridge.allow_production_writes' => false]);
        [$user, $organization] = $this->userWithOrganization();
        $profile = app(ProductSyncProfileProvisioner::class)->ensureDefault($organization);

        $this->actingAs($user)
            ->post('/product-sync/profile', $this->profilePayload(['mode' => 'production']))
            ->assertSessionHasErrors('mode');

        $this->assertSame('preview_only', $profile->fresh()->mode);
    }

    public function test_preview_run_creates_run_and_items_from_preview_plan(): void
    {
        Http::fake();
        [$user, $organization] = $this->userWithOrganization();
        $profile = app(ProductSyncProfileProvisioner::class)->ensureDefault($organization);
        $this->previewPlan($organization);

        $this->actingAs($user)
            ->post('/product-sync/preview-run')
            ->assertRedirect();

        $run = ProductSyncRun::query()->firstOrFail();

        $this->assertSame($profile->id, $run->product_sync_profile_id);
        $this->assertSame('preview', $run->run_type);
        $this->assertSame('draft', $run->status);
        $this->assertSame('preview_only', $run->mode);
        $this->assertSame('selected_only', $run->scope);
        $this->assertSame(1, $run->total_candidates);
        $this->assertSame(1, $run->total_ready);
        $this->assertSame(1, $run->total_pending);
        $this->assertDatabaseHas('product_sync_run_items', [
            'product_sync_run_id' => $run->id,
            'woo_item_key' => 'product:123',
            'woo_sku' => 'BOOT-24',
            'detected_gtin' => '7040000000012',
            'validation_status' => 'warning',
            'sync_status' => 'not_started',
        ]);
        $this->assertSame(0, ProductMapping::query()->count());
        Http::assertNothingSent();
    }

    public function test_max_products_per_run_is_enforced(): void
    {
        [$user, $organization] = $this->userWithOrganization();
        $profile = app(ProductSyncProfileProvisioner::class)->ensureDefault($organization);
        $profile->update(['max_products_per_run' => 1]);
        $this->previewPlan($organization, [
            $this->planRow(123, 'BOOT-24', '7040000000012'),
            $this->planRow(124, 'BOOT-25', '7040000000013'),
        ]);

        $this->actingAs($user)
            ->post('/product-sync/preview-run')
            ->assertRedirect();

        $this->assertSame(1, ProductSyncRun::query()->firstOrFail()->items()->count());
    }

    public function test_validation_warns_for_missing_gtin_when_sku_exists_and_gtin_not_required(): void
    {
        [$user, $organization] = $this->userWithOrganization();
        app(ProductSyncProfileProvisioner::class)->ensureDefault($organization);
        $this->previewPlan($organization, [
            $this->planRow(123, 'BOOT-24', null),
        ]);

        $this->actingAs($user)
            ->post('/product-sync/preview-run')
            ->assertRedirect();

        $item = ProductSyncRun::query()->firstOrFail()->items()->firstOrFail();

        $this->assertSame('warning', $item->validation_status);
        $this->assertNotContains('Missing GTIN/EAN candidate.', $item->validation_errors_json);
        $this->assertContains('Missing GTIN/EAN candidate; SKU fallback may be used if the SKU is unique and approved.', $item->validation_warnings_json);
    }

    public function test_validation_blocks_missing_sku_and_gtin_when_gtin_required(): void
    {
        [$user, $organization] = $this->userWithOrganization();
        app(ProductSyncProfileProvisioner::class)->ensureDefault($organization)
            ->update(['require_gtin' => true]);
        $this->previewPlan($organization, [
            $this->planRow(123, null, null),
        ]);

        $this->actingAs($user)
            ->post('/product-sync/preview-run')
            ->assertRedirect();

        $item = ProductSyncRun::query()->firstOrFail()->items()->firstOrFail();

        $this->assertSame('blocked', $item->validation_status);
        $this->assertContains('Missing SKU.', $item->validation_errors_json);
        $this->assertContains('Missing GTIN/EAN candidate.', $item->validation_errors_json);
    }

    public function test_validation_blocks_duplicate_gtin_in_same_run(): void
    {
        [$user, $organization] = $this->userWithOrganization();
        app(ProductSyncProfileProvisioner::class)->ensureDefault($organization);
        $this->previewPlan($organization, [
            $this->planRow(123, 'BOOT-24', '7040000000012'),
            $this->planRow(124, 'BOOT-25', '7040000000012'),
        ]);

        $this->actingAs($user)
            ->post('/product-sync/preview-run')
            ->assertRedirect();

        $items = ProductSyncRun::query()->firstOrFail()->items()->get();

        $this->assertSame(2, $items->where('validation_status', 'blocked')->count());
        $this->assertContains('Duplicate GTIN/EAN within sync run.', $items->first()->validation_errors_json);
    }

    public function test_validation_supports_variation_item_keys(): void
    {
        [$user, $organization] = $this->userWithOrganization();
        app(ProductSyncProfileProvisioner::class)->ensureDefault($organization);
        $this->previewPlan($organization, [
            $this->planRow(456, 'BOOT-24-BLUE', '7040000000456', 'variation', 123),
        ]);

        $this->actingAs($user)
            ->post('/product-sync/preview-run')
            ->assertRedirect();

        $item = ProductSyncRun::query()->firstOrFail()->items()->firstOrFail();

        $this->assertSame('variation:456', $item->woo_item_key);
        $this->assertSame(123, $item->woo_product_id);
        $this->assertSame(456, $item->woo_variation_id);
        $this->assertNotSame('blocked', $item->validation_status);
    }

    public function test_variation_without_parent_context_is_blocked(): void
    {
        [$user, $organization] = $this->userWithOrganization();
        app(ProductSyncProfileProvisioner::class)->ensureDefault($organization);
        $this->previewPlan($organization, [
            $this->planRow(456, 'BOOT-24-BLUE', '7040000000456', 'variation'),
        ]);

        $this->actingAs($user)
            ->post('/product-sync/preview-run')
            ->assertRedirect();

        $item = ProductSyncRun::query()->firstOrFail()->items()->firstOrFail();

        $this->assertSame('blocked', $item->validation_status);
        $this->assertContains('Variation is missing parent product context.', $item->validation_errors_json);
    }

    public function test_sync_runs_page_paginates_items_and_filters_statuses(): void
    {
        [$user, $organization] = $this->userWithOrganization();
        $run = $this->runWithItems($organization, [
            ['sku' => 'READY-1', 'gtin' => '7040000001001', 'sync_status' => 'not_started', 'validation_status' => 'warning'],
            ['sku' => 'FAILED-1', 'gtin' => '7040000001002', 'sync_status' => 'failed', 'validation_status' => 'blocked'],
        ]);

        $this->actingAs($user)
            ->get('/product-sync/runs')
            ->assertOk()
            ->assertSee('#' . $run->id);

        $this->actingAs($user)
            ->get('/product-sync/runs/' . $run->id . '?sync_status=failed')
            ->assertOk()
            ->assertSee('FAILED-1')
            ->assertDontSee('READY-1');

        $this->actingAs($user)
            ->get('/product-sync/runs/' . $run->id . '?validation_status=warning')
            ->assertOk()
            ->assertSee('READY-1')
            ->assertDontSee('FAILED-1');
    }

    public function test_sync_run_search_works_by_sku_and_gtin(): void
    {
        [$user, $organization] = $this->userWithOrganization();
        $run = $this->runWithItems($organization, [
            ['sku' => 'FIND-ME', 'gtin' => '7040000002001'],
            ['sku' => 'OTHER', 'gtin' => '7040000002002'],
        ]);

        $this->actingAs($user)
            ->get('/product-sync/runs/' . $run->id . '?q=FIND-ME')
            ->assertOk()
            ->assertSee('FIND-ME')
            ->assertDontSee('OTHER');

        $this->actingAs($user)
            ->get('/product-sync/runs/' . $run->id . '?q=7040000002002')
            ->assertOk()
            ->assertSee('OTHER')
            ->assertDontSee('FIND-ME');
    }

    public function test_product_sync_event_deduplication_works(): void
    {
        [, $organization] = $this->userWithOrganization();
        $recorder = app(ProductSyncEventRecorder::class);

        $first = $recorder->recordWooChange($organization, 'product_updated', 123, null, ['name' => 'Boot']);
        $second = $recorder->recordWooChange($organization, 'product_updated', 123, null, ['name' => 'Boot changed again']);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, ProductSyncEvent::query()->count());
        $this->assertDatabaseHas('product_sync_events', [
            'organization_id' => $organization->id,
            'event_type' => 'product_updated',
            'woo_item_key' => 'product:123',
            'status' => 'pending',
        ]);
    }

    public function test_normal_dashboard_does_not_expose_raw_technical_payloads(): void
    {
        [$user] = $this->userWithOrganization();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Ready')
            ->assertDontSee('Webhook URLs')
            ->assertDontSee('payload')
            ->assertDontSee('idempotency')
            ->assertDontSee('queue workers')
            ->assertDontSee('API body')
            ->assertDontSee('Mapping Preview Lab')
            ->assertDontSee('Discover products')
            ->assertDontSee('Create preview run');
    }

    public function test_advanced_page_is_separate(): void
    {
        [$user] = $this->userWithOrganization();

        $this->actingAs($user)
            ->get('/advanced')
            ->assertOk()
            ->assertSee('Technical settings')
            ->assertSee('Testing Lab')
            ->assertSee('Webhooks')
            ->assertSee('API Settings');
    }

    public function test_testing_lab_contains_preview_run_action(): void
    {
        [$user, $organization] = $this->userWithOrganization();
        app(ProductSyncProfileProvisioner::class)->ensureDefault($organization);
        $this->previewPlan($organization);

        $this->actingAs($user)
            ->get('/lab')
            ->assertOk()
            ->assertSee('Testing Lab')
            ->assertSee('Create preview run')
            ->assertSee('Open mapping preview');
    }

    public function test_woocommerce_plugin_remains_thin_without_heavy_queries(): void
    {
        $plugin = file_get_contents(base_path('../woocommerce-plugin/lilleprinsen-front-sync.php'));

        $this->assertIsString($plugin);
        $this->assertStringNotContainsString('WP_Query', $plugin);
        $this->assertStringNotContainsString('wc_get_products', $plugin);
        $this->assertStringNotContainsString('save_post_product', $plugin);
    }

    private function userWithOrganization(): array
    {
        $user = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $organization = Organization::query()->create([
            'name' => 'Lilleprinsen',
            'slug' => 'lilleprinsen',
            'environment' => 'staging',
            'status' => 'active',
        ]);

        $organization->users()->attach($user->id, ['role' => 'owner']);

        return [$user, $organization];
    }

    private function previewPlan(Organization $organization, ?array $rows = null): ProductSyncPreviewPlan
    {
        $rows ??= [$this->planRow(123, 'BOOT-24', '7040000000012')];

        return ProductSyncPreviewPlan::query()->create([
            'organization_id' => $organization->id,
            'created_by_user_id' => $organization->users()->first()->id,
            'status' => 'ready',
            'selected_count' => count($rows),
            'summary_json' => ['selected_count' => count($rows), 'ready_count' => count($rows), 'blocked_count' => 0],
            'plan_json' => ['rows' => $rows],
            'validation_json' => [],
        ]);
    }

    private function planRow(int $id, ?string $sku, ?string $gtin, string $type = 'simple', ?int $parentProductId = null): array
    {
        return [
            'woo_product' => [
                'id' => $id,
                'parent_product_id' => $parentProductId,
                'name' => "Woo Product {$id}",
                'sku' => $sku,
                'type' => $type,
            ],
            'gtin_candidate' => [
                'key' => $gtin ? 'Zettle_barcode' : null,
                'value' => $gtin,
                'confidence' => $gtin ? 'exact_known_field' : 'none',
            ],
            'front_match' => [
                'status' => $gtin ? 'matched_existing_front_product' : 'no_match',
            ],
            'proposed_front_payload' => [
                'name' => "Woo Product {$id}",
                'number' => $sku,
                'variant' => $sku,
                'brand' => null,
                'groupName' => 'Shoes',
                'subgroupName' => 'Boots',
                'price_candidate' => '599',
                'productSizes' => [
                    [
                        'gtin' => $gtin,
                        'externalSKU' => $sku,
                    ],
                ],
            ],
            'status' => $sku && $gtin && $type !== 'variable' ? 'ready' : 'blocked',
            'blocks' => [],
            'warnings' => ['Brand mapping is uncertain.'],
            'needs_confirmation' => ['product number/variant strategy'],
        ];
    }

    private function profilePayload(array $overrides = []): array
    {
        return $overrides + [
            'mode' => 'preview_only',
            'sync_scope' => 'selected_only',
            'max_products_per_run' => 100,
            'sync_only_opted_in_products' => '1',
            'include_simple_products' => '1',
            'include_variable_products' => '1',
            'include_variations' => '1',
            'include_out_of_stock_products' => '1',
            'exclude_discontinued_products' => '1',
            'require_sku' => '1',
            'require_gtin' => '1',
            'require_price' => '1',
            'max_products_per_batch' => 25,
            'woo_page_size' => 50,
            'front_page_size' => 50,
            'product_identity_strategy' => 'woo_id_as_front_extid',
            'gtin_field_strategy' => 'auto_detect',
            'price_strategy' => 'regular_price_only',
            'stock_strategy' => 'do_not_sync_stock_yet',
        ];
    }

    private function runWithItems(Organization $organization, array $items): ProductSyncRun
    {
        $profile = app(ProductSyncProfileProvisioner::class)->ensureDefault($organization);
        $run = ProductSyncRun::query()->create([
            'organization_id' => $organization->id,
            'product_sync_profile_id' => $profile->id,
            'run_type' => 'preview',
            'status' => 'draft',
            'mode' => 'preview_only',
            'scope' => 'selected_only',
            'total_candidates' => count($items),
            'total_ready' => count($items),
            'total_pending' => count($items),
            'summary_json' => ['preview_only' => true],
        ]);

        foreach ($items as $index => $item) {
            ProductSyncRunItem::query()->create([
                'organization_id' => $organization->id,
                'product_sync_run_id' => $run->id,
                'woo_product_id' => 1000 + $index,
                'woo_item_key' => 'product:' . (1000 + $index),
                'woo_name' => 'Product ' . ($index + 1),
                'woo_type' => 'simple',
                'woo_sku' => $item['sku'],
                'detected_gtin' => $item['gtin'],
                'front_match_status' => 'no_match',
                'validation_status' => $item['validation_status'] ?? 'warning',
                'sync_status' => $item['sync_status'] ?? 'not_started',
                'validation_errors_json' => [],
                'validation_warnings_json' => [],
            ]);
        }

        return $run;
    }
}
