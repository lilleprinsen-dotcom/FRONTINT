<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\AuditLog;
use App\Models\Connection;
use App\Models\ConnectionDiscoverySnapshot;
use App\Models\ProductMapping;
use App\Models\ProductSyncEvent;
use App\Models\ProductSyncPreviewPlan;
use App\Models\ProductSyncProfile;
use App\Models\ProductSyncRun;
use App\Models\ProductSyncRunItem;
use App\Models\User;
use App\Services\Credentials\CredentialVault;
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

    public function test_front_write_dry_run_requires_limited_write_mode(): void
    {
        Http::fake();
        [$user, $organization] = $this->userWithOrganization();
        $this->frontConnection($organization);
        $run = $this->runWithItems($organization, [
            ['sku' => 'READY-1', 'gtin' => '7040000002001', 'validation_status' => 'ready'],
        ]);

        $this->actingAs($user)
            ->post('/product-sync/runs/' . $run->id . '/front-dry-run', [
                'item_ids' => [$run->items()->firstOrFail()->id],
            ])
            ->assertSessionHasErrors('front_dry_run');

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'action' => 'front_product_write_dry_run_prepared',
        ]);
        Http::assertNothingSent();
    }

    public function test_front_write_dry_run_requires_front_connection(): void
    {
        Http::fake();
        [$user, $organization] = $this->userWithOrganization();
        $profile = app(ProductSyncProfileProvisioner::class)->ensureDefault($organization);
        $profile->update(['mode' => 'limited_write_test']);
        $run = $this->runWithItems($organization, [
            ['sku' => 'READY-1', 'gtin' => '7040000002001', 'validation_status' => 'ready'],
        ]);

        $this->actingAs($user)
            ->post('/product-sync/runs/' . $run->id . '/front-dry-run', [
                'item_ids' => [$run->items()->firstOrFail()->id],
            ])
            ->assertSessionHasErrors('front_dry_run');

        $this->assertSame(1, AuditLog::query()->where('action', 'front_product_write_dry_run_prepared')->count());
        Http::assertNothingSent();
    }

    public function test_front_write_dry_run_rejects_blocked_items(): void
    {
        Http::fake();
        [$user, $organization] = $this->userWithOrganization();
        $this->frontConnection($organization);
        $profile = app(ProductSyncProfileProvisioner::class)->ensureDefault($organization);
        $profile->update(['mode' => 'limited_write_test']);
        $run = $this->runWithItems($organization, [
            ['sku' => 'BLOCKED-1', 'gtin' => null, 'validation_status' => 'blocked'],
        ]);

        $this->actingAs($user)
            ->post('/product-sync/runs/' . $run->id . '/front-dry-run', [
                'item_ids' => [$run->items()->firstOrFail()->id],
            ])
            ->assertSessionHasErrors('front_dry_run');

        Http::assertNothingSent();
    }

    public function test_front_write_dry_run_caps_selection_at_ten(): void
    {
        [$user, $organization] = $this->userWithOrganization();
        $this->frontConnection($organization);
        app(ProductSyncProfileProvisioner::class)->ensureDefault($organization)
            ->update(['mode' => 'limited_write_test']);
        $run = $this->runWithItems($organization, collect(range(1, 11))
            ->map(fn (int $index): array => [
                'sku' => 'READY-' . $index,
                'gtin' => '70400000030' . str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'validation_status' => 'ready',
            ])
            ->all());

        $this->actingAs($user)
            ->post('/product-sync/runs/' . $run->id . '/front-dry-run', [
                'item_ids' => $run->items()->pluck('id')->all(),
            ])
            ->assertSessionHasErrors('item_ids');
    }

    public function test_front_write_dry_run_shows_exact_payload_and_audits_without_http_calls(): void
    {
        Http::fake();
        [$user, $organization] = $this->userWithOrganization();
        $this->frontConnection($organization);
        app(ProductSyncProfileProvisioner::class)->ensureDefault($organization)
            ->update(['mode' => 'limited_write_test']);
        $run = $this->runWithItems($organization, [
            [
                'sku' => 'READY-1',
                'gtin' => '7040000002001',
                'validation_status' => 'ready',
                'sale_price' => '499',
            ],
            [
                'sku' => 'WARN-1',
                'gtin' => null,
                'validation_status' => 'warning',
            ],
        ]);
        $itemIds = $run->items()->pluck('id')->all();

        $response = $this->actingAs($user)
            ->post('/product-sync/runs/' . $run->id . '/front-dry-run', [
                'item_ids' => $itemIds,
            ])
            ->assertRedirect();

        $location = $response->headers->get('Location');
        $this->assertIsString($location);

        $this->actingAs($user)
            ->get($location)
            ->assertOk()
            ->assertSee('Front Product Dry-Run')
            ->assertSee('READY-1')
            ->assertSee('7040000002001')
            ->assertSee('Regular: 599')
            ->assertSee('Sale: 499')
            ->assertSee('future PriceListV2 candidate')
            ->assertSee('No Front API calls are made');

        $audit = AuditLog::query()->where('action', 'front_product_write_dry_run_prepared')->firstOrFail();
        $this->assertSame('ready', $audit->metadata_json['status']);
        $this->assertFalse($audit->metadata_json['external_api_calls']);
        $this->assertFalse($audit->metadata_json['writes_performed']);
        $this->assertStringNotContainsString('api_key', json_encode($audit->metadata_json));
        Http::assertNothingSent();
    }

    public function test_limited_front_write_test_calls_documented_product_endpoint_and_creates_mapping(): void
    {
        Http::fake([
            'https://front.example.test/restapi/V2/api/products/woo-product-1000' => Http::response([], 404),
            'https://front.example.test/restapi/V2/api/Product/gtin/7040000002001' => Http::response([], 404),
            'https://front.example.test/restapi/V2/api/products' => Http::response([
                'id' => 'front-uuid-1',
                'extId' => 'woo-product-1000',
                'productid' => 9876,
                'number' => 'READY-1',
                'variant' => 'READY-1',
                'productSizes' => [
                    [
                        'identity' => 'front-size-identity-1',
                        'gtin' => '7040000002001',
                        'externalSKU' => 'READY-1',
                    ],
                ],
            ]),
        ]);
        [$user, $organization] = $this->userWithOrganization();
        $this->wooConnection($organization);
        $this->frontConnection($organization);
        app(ProductSyncProfileProvisioner::class)->ensureDefault($organization)
            ->update(['mode' => 'limited_write_test']);
        $run = $this->runWithItems($organization, [
            ['sku' => 'READY-1', 'gtin' => '7040000002001', 'validation_status' => 'ready'],
        ]);
        $item = $run->items()->firstOrFail();

        $this->actingAs($user)
            ->post('/product-sync/runs/' . $run->id . '/limited-front-write-test', [
                'item_ids' => [$item->id],
            ])
            ->assertRedirect();

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->method() === 'POST'
                && (string) $request->url() === 'https://front.example.test/restapi/V2/api/products'
                && $request->hasHeader('x-api-key', 'front-secret-key')
                && ($payload['extId'] ?? null) === 'woo-product-1000'
                && ($payload['number'] ?? null) === 'READY-1'
                && ($payload['productSizes'][0]['gtin'] ?? null) === '7040000002001';
        });
        Http::assertSentCount(3);

        $item->refresh();
        $this->assertSame('synced', $item->sync_status);
        $this->assertSame('9876', $item->front_product_id);
        $this->assertSame('woo-product-1000', $item->front_product_ext_id);
        $this->assertSame('front-size-identity-1', $item->front_identity);
        $this->assertSame('READY-1', $item->front_external_sku);
        $this->assertNotNull($item->synced_at);
        $this->assertSame('POST /api/products', $item->last_request_summary_json['endpoint']);
        $this->assertArrayNotHasKey('x-api-key', $item->last_request_summary_json);
        $this->assertDatabaseHas('product_mappings', [
            'organization_id' => $organization->id,
            'woo_item_key' => 'product:1000',
            'front_product_id' => '9876',
            'front_product_ext_id' => 'woo-product-1000',
            'front_identity' => 'front-size-identity-1',
            'external_sku' => 'READY-1',
            'sync_status' => 'synced',
        ]);
        $auditJson = AuditLog::query()->where('action', 'limited_front_product_write_test')->get()->toJson();
        $this->assertStringNotContainsString('front-secret-key', $auditJson);
    }

    public function test_limited_front_write_test_does_not_call_http_when_safety_gates_fail(): void
    {
        Http::fake();
        [$user, $organization] = $this->userWithOrganization();
        $this->frontConnection($organization);
        $run = $this->runWithItems($organization, [
            ['sku' => 'READY-1', 'gtin' => '7040000002001', 'validation_status' => 'ready'],
        ]);

        $this->actingAs($user)
            ->post('/product-sync/runs/' . $run->id . '/limited-front-write-test', [
                'item_ids' => [$run->items()->firstOrFail()->id],
            ])
            ->assertRedirect();

        Http::assertNothingSent();
        $this->assertSame('not_started', $run->items()->firstOrFail()->sync_status);
        $this->assertSame(0, ProductMapping::query()->count());
    }

    public function test_limited_front_write_test_rejects_blocked_items_before_http(): void
    {
        Http::fake();
        [$user, $organization] = $this->userWithOrganization();
        $this->frontConnection($organization);
        app(ProductSyncProfileProvisioner::class)->ensureDefault($organization)
            ->update(['mode' => 'limited_write_test']);
        $run = $this->runWithItems($organization, [
            ['sku' => 'BLOCKED-1', 'gtin' => null, 'validation_status' => 'blocked'],
        ]);

        $this->actingAs($user)
            ->post('/product-sync/runs/' . $run->id . '/limited-front-write-test', [
                'item_ids' => [$run->items()->firstOrFail()->id],
            ])
            ->assertRedirect();

        Http::assertNothingSent();
        $this->assertSame('not_started', $run->items()->firstOrFail()->sync_status);
    }

    public function test_limited_front_write_test_requires_front_api_key_before_http(): void
    {
        Http::fake();
        [$user, $organization] = $this->userWithOrganization();
        Connection::query()->create([
            'organization_id' => $organization->id,
            'type' => 'front_systems',
            'name' => 'Front staging',
            'base_url' => 'https://front.example.test/restapi/V2',
            'status' => 'success',
        ]);
        app(ProductSyncProfileProvisioner::class)->ensureDefault($organization)
            ->update(['mode' => 'limited_write_test']);
        $run = $this->runWithItems($organization, [
            ['sku' => 'READY-1', 'gtin' => '7040000002001', 'validation_status' => 'ready'],
        ]);

        $this->actingAs($user)
            ->post('/product-sync/runs/' . $run->id . '/limited-front-write-test', [
                'item_ids' => [$run->items()->firstOrFail()->id],
            ])
            ->assertRedirect();

        Http::assertNothingSent();
        $this->assertSame('not_started', $run->items()->firstOrFail()->sync_status);
    }

    public function test_limited_front_write_test_caps_selection_at_ten_before_http(): void
    {
        Http::fake();
        [$user, $organization] = $this->userWithOrganization();
        $this->frontConnection($organization);
        app(ProductSyncProfileProvisioner::class)->ensureDefault($organization)
            ->update(['mode' => 'limited_write_test']);
        $run = $this->runWithItems($organization, collect(range(1, 11))
            ->map(fn (int $index): array => [
                'sku' => 'READY-' . $index,
                'gtin' => '70400000040' . str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'validation_status' => 'ready',
            ])
            ->all());

        $this->actingAs($user)
            ->post('/product-sync/runs/' . $run->id . '/limited-front-write-test', [
                'item_ids' => $run->items()->pluck('id')->all(),
            ])
            ->assertSessionHasErrors('item_ids');

        Http::assertNothingSent();
    }

    public function test_limited_front_write_test_marks_failed_front_response_without_mapping(): void
    {
        Http::fake([
            'https://front.example.test/restapi/V2/api/products/woo-product-1000' => Http::response([], 404),
            'https://front.example.test/restapi/V2/api/Product/gtin/7040000002001' => Http::response([], 404),
            'https://front.example.test/restapi/V2/api/products' => Http::response(['message' => 'Bad request'], 422),
        ]);
        [$user, $organization] = $this->userWithOrganization();
        $this->wooConnection($organization);
        $this->frontConnection($organization);
        app(ProductSyncProfileProvisioner::class)->ensureDefault($organization)
            ->update(['mode' => 'limited_write_test']);
        $run = $this->runWithItems($organization, [
            ['sku' => 'READY-1', 'gtin' => '7040000002001', 'validation_status' => 'ready'],
        ]);

        $this->actingAs($user)
            ->post('/product-sync/runs/' . $run->id . '/limited-front-write-test', [
                'item_ids' => [$run->items()->firstOrFail()->id],
            ])
            ->assertRedirect();

        $item = $run->items()->firstOrFail();
        $this->assertSame('failed', $item->sync_status);
        $this->assertSame('HTTP 422', $item->last_error);
        $this->assertSame(422, $item->last_response_summary_json['http_status']);
        $this->assertSame(0, ProductMapping::query()->count());
    }

    public function test_staging_batch_run_can_be_created_from_woo_discovery_with_variations(): void
    {
        Http::fake();
        [$user, $organization] = $this->userWithOrganization();
        $profile = app(ProductSyncProfileProvisioner::class)->ensureDefault($organization);
        $profile->update(['mode' => 'staging_batch']);
        $this->wooConnection($organization);
        $snapshot = $this->wooDiscoverySnapshot($organization);

        $this->actingAs($user)
            ->post('/product-sync/staging-batch-run', [
                'woo_item_keys' => ['product:123', 'variation:456'],
            ])
            ->assertRedirect();

        $run = ProductSyncRun::query()->firstOrFail();
        $items = $run->items()->orderBy('id')->get();

        $this->assertSame('staging_batch', $run->run_type);
        $this->assertSame('staging_batch', $run->mode);
        $this->assertSame(2, $items->count());
        $this->assertSame(1, $run->total_variations);
        $this->assertSame('product:123', $items[0]->woo_item_key);
        $this->assertSame('variation:456', $items[1]->woo_item_key);
        $this->assertSame(123, $items[1]->woo_product_id);
        $this->assertSame(456, $items[1]->woo_variation_id);
        $this->assertSame('24', $items[1]->proposed_front_payload_json['productSizes'][0]['label']);
        $this->assertSame($snapshot->id, $run->cursor_json['woo_snapshot_id']);
        Http::assertNothingSent();
    }

    public function test_staging_batch_sync_creates_when_front_lookup_does_not_find_existing_product(): void
    {
        Http::fake([
            'https://front.example.test/restapi/V2/api/products/woo-product-1000' => Http::response([], 404),
            'https://front.example.test/restapi/V2/api/Product/gtin/7040000002001' => Http::response([], 404),
            'https://front.example.test/restapi/V2/api/products' => Http::response([
                'productid' => 222,
                'extId' => 'woo-product-1000',
                'productSizes' => [
                    ['identity' => 'identity-222', 'externalSKU' => 'READY-1'],
                ],
            ]),
        ]);
        [$user, $organization] = $this->userWithOrganization();
        $this->wooConnection($organization);
        $this->frontConnection($organization);
        app(ProductSyncProfileProvisioner::class)->ensureDefault($organization)
            ->update(['mode' => 'staging_batch']);
        $run = $this->runWithItems($organization, [
            ['sku' => 'READY-1', 'gtin' => '7040000002001', 'validation_status' => 'ready'],
        ]);

        $this->actingAs($user)
            ->post('/product-sync/runs/' . $run->id . '/staging-batch-sync')
            ->assertRedirect();

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->method() === 'POST'
                && (string) $request->url() === 'https://front.example.test/restapi/V2/api/products'
                && ($payload['description'] ?? null) === 'Full product description for store staff.'
                && ($payload['internalDescription'] ?? null) === 'Short product note.'
                && ($payload['tags'] ?? null) === 'summer, staff-pick'
                && ($payload['images'] ?? null) === [
                    'https://example.test/image-1.jpg',
                    'https://example.test/image-1-back.jpg',
                ];
        });
        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/wp-json/'));
        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/api/Stock'));
        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/api/Pricelist'));
        $this->assertSame('synced', $run->items()->firstOrFail()->sync_status);
        $this->assertTrue($run->items()->firstOrFail()->last_request_summary_json['description_included']);
        $this->assertSame(2, $run->items()->firstOrFail()->last_request_summary_json['tag_count']);
        $this->assertSame(2, $run->items()->firstOrFail()->last_request_summary_json['image_count']);
        $this->assertDatabaseHas('product_mappings', [
            'organization_id' => $organization->id,
            'woo_item_key' => 'product:1000',
            'front_product_id' => '222',
        ]);
    }

    public function test_staging_batch_sends_regular_price_and_keeps_sale_price_as_future_pricelist_candidate(): void
    {
        Http::fake([
            'https://front.example.test/restapi/V2/api/products/woo-product-1000' => Http::response([], 404),
            'https://front.example.test/restapi/V2/api/Product/gtin/7040000002001' => Http::response([], 404),
            'https://front.example.test/restapi/V2/api/products' => Http::response([
                'productid' => 222,
                'extId' => 'woo-product-1000',
                'price' => 599,
                'productSizes' => [
                    ['identity' => 'identity-222', 'externalSKU' => 'READY-1'],
                ],
            ]),
        ]);
        [$user, $organization] = $this->userWithOrganization();
        $this->wooConnection($organization);
        $this->frontConnection($organization);
        app(ProductSyncProfileProvisioner::class)->ensureDefault($organization)
            ->update(['mode' => 'staging_batch']);
        $run = $this->runWithItems($organization, [
            ['sku' => 'READY-1', 'gtin' => '7040000002001', 'validation_status' => 'ready', 'sale_price' => '499'],
        ]);

        $this->actingAs($user)
            ->post('/product-sync/runs/' . $run->id . '/staging-batch-sync')
            ->assertRedirect();

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->method() === 'POST'
                && (string) $request->url() === 'https://front.example.test/restapi/V2/api/products'
                && ($payload['price'] ?? null) === 599
                && ! array_key_exists('sale_price', $payload)
                && ! array_key_exists('salePrice', $payload)
                && ! array_key_exists('pricelists', $payload);
        });

        $summary = $run->fresh()->items()->firstOrFail()->last_request_summary_json;

        $this->assertFalse($summary['includes_sale_price']);
        $this->assertSame(599, $summary['regular_price']);
        $this->assertSame('499', $summary['sale_price_candidate']);
        $this->assertSame('future PriceListV2 candidate', $summary['sale_price_destination']);
    }

    public function test_sale_price_sync_posts_pricelist_v2_without_product_stock_or_woo_writes(): void
    {
        Http::fake([
            'https://front.example.test/restapi/V2/api/PricelistV2' => Http::response([
                'pricelistId' => 77,
                'name' => 'Lilleprinsen Sale',
                'productCount' => 1,
            ]),
        ]);
        [$user, $organization] = $this->userWithOrganization();
        $this->wooConnection($organization);
        $this->frontConnection($organization);
        app(ProductSyncProfileProvisioner::class)->ensureDefault($organization)
            ->update([
                'mode' => 'staging_batch',
                'price_strategy' => 'pricelist_v2_later',
                'sale_price_list_name' => 'Lilleprinsen Sale',
            ]);
        $run = $this->runWithItems($organization, [
            [
                'sku' => 'READY-1',
                'gtin' => '7040000002001',
                'validation_status' => 'ready',
                'sync_status' => 'synced',
                'sale_price' => '499',
                'front_product_ext_id' => 'woo-product-1000',
            ],
        ]);

        $this->actingAs($user)
            ->post('/product-sync/runs/' . $run->id . '/sale-prices')
            ->assertRedirect();

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->method() === 'POST'
                && (string) $request->url() === 'https://front.example.test/restapi/V2/api/PricelistV2'
                && ($payload['name'] ?? null) === 'Lilleprinsen Sale'
                && ($payload['prices'][0]['productExtId'] ?? null) === 'woo-product-1000'
                && ($payload['prices'][0]['price'] ?? null) === 499;
        });
        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/wp-json/'));
        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/api/products'));
        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/api/Stock'));

        $item = $run->fresh()->items()->firstOrFail();
        $this->assertSame('synced', $item->sale_price_sync_status);
        $this->assertSame(77, $item->sale_price_last_response_summary_json['pricelistId']);
        $this->assertSame('POST /api/PricelistV2', $item->sale_price_last_request_summary_json['endpoint']);
        $this->assertArrayNotHasKey('x-api-key', $item->sale_price_last_request_summary_json);
        $auditJson = AuditLog::query()->where('action', 'front_sale_price_sync')->get()->toJson();
        $this->assertStringNotContainsString('front-secret-key', $auditJson);
    }

    public function test_sale_price_sync_uses_gtin_when_front_ext_id_is_missing(): void
    {
        Http::fake([
            'https://front.example.test/restapi/V2/api/PricelistV2' => Http::response(['pricelistId' => 77]),
        ]);
        [$user, $organization] = $this->userWithOrganization();
        $this->wooConnection($organization);
        $this->frontConnection($organization);
        app(ProductSyncProfileProvisioner::class)->ensureDefault($organization)
            ->update(['mode' => 'staging_batch', 'price_strategy' => 'pricelist_v2_later']);
        $run = $this->runWithItems($organization, [
            [
                'sku' => 'READY-1',
                'gtin' => '7040000002001',
                'validation_status' => 'ready',
                'sync_status' => 'synced',
                'sale_price' => '499',
            ],
        ]);

        $this->actingAs($user)
            ->post('/product-sync/runs/' . $run->id . '/sale-prices')
            ->assertRedirect();

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return ($payload['prices'][0]['gtin'] ?? null) === '7040000002001'
                && ! array_key_exists('productExtId', $payload['prices'][0]);
        });
    }

    public function test_sale_price_sync_does_not_call_http_when_gates_fail(): void
    {
        Http::fake();
        [$user, $organization] = $this->userWithOrganization();
        $this->wooConnection($organization);
        $this->frontConnection($organization);
        $run = $this->runWithItems($organization, [
            [
                'sku' => 'READY-1',
                'gtin' => '7040000002001',
                'validation_status' => 'ready',
                'sync_status' => 'synced',
                'sale_price' => '499',
                'front_product_ext_id' => 'woo-product-1000',
            ],
        ]);

        $this->actingAs($user)
            ->post('/product-sync/runs/' . $run->id . '/sale-prices')
            ->assertRedirect();

        Http::assertNothingSent();
        $this->assertSame('not_applicable', $run->fresh()->items()->firstOrFail()->sale_price_sync_status);
    }

    public function test_sale_price_sync_marks_failed_front_response_and_retry_can_succeed(): void
    {
        Http::fake([
            'https://front.example.test/restapi/V2/api/PricelistV2' => Http::sequence()
                ->push(['message' => 'Bad sale price'], 422)
                ->push(['pricelistId' => 88, 'name' => 'WooCommerce Sale Prices'], 200),
        ]);
        [$user, $organization] = $this->userWithOrganization();
        $this->wooConnection($organization);
        $this->frontConnection($organization);
        app(ProductSyncProfileProvisioner::class)->ensureDefault($organization)
            ->update(['mode' => 'staging_batch', 'price_strategy' => 'pricelist_v2_later']);
        $run = $this->runWithItems($organization, [
            [
                'sku' => 'READY-1',
                'gtin' => '7040000002001',
                'validation_status' => 'ready',
                'sync_status' => 'synced',
                'sale_price' => '499',
                'front_product_ext_id' => 'woo-product-1000',
            ],
        ]);

        $this->actingAs($user)
            ->post('/product-sync/runs/' . $run->id . '/sale-prices')
            ->assertRedirect();

        $item = $run->fresh()->items()->firstOrFail();
        $this->assertSame('failed', $item->sale_price_sync_status);
        $this->assertSame('HTTP 422', $item->sale_price_last_error);

        $this->actingAs($user)
            ->post('/product-sync/runs/' . $run->id . '/retry-sale-prices')
            ->assertRedirect();

        $item = $run->fresh()->items()->firstOrFail();
        $this->assertSame('synced', $item->sale_price_sync_status);
        $this->assertSame(2, $item->sale_price_attempt_count);
        $this->assertSame(88, $item->sale_price_last_response_summary_json['pricelistId']);
    }

    public function test_stock_sync_posts_partial_stock_adjust_without_woo_product_or_price_writes(): void
    {
        Http::fake([
            'https://front.example.test/restapi/V2/api/Stock/adjust' => Http::response('Ok'),
        ]);
        [$user, $organization] = $this->userWithOrganization();
        $this->wooConnection($organization);
        $this->frontConnection($organization);
        app(ProductSyncProfileProvisioner::class)->ensureDefault($organization)
            ->update([
                'mode' => 'staging_batch',
                'stock_strategy' => 'stock_sync_later',
                'front_stock_id' => 2001,
            ]);
        $run = $this->runWithItems($organization, [
            [
                'sku' => 'READY-1',
                'gtin' => '7040000002001',
                'validation_status' => 'ready',
                'sync_status' => 'synced',
                'stock_quantity' => 7,
                'front_external_sku' => 'READY-1',
            ],
        ]);

        $this->actingAs($user)
            ->post('/product-sync/runs/' . $run->id . '/stock')
            ->assertRedirect();

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->method() === 'POST'
                && (string) $request->url() === 'https://front.example.test/restapi/V2/api/Stock/adjust'
                && ($payload['stockId'] ?? null) === 2001
                && ($payload['isCompleteStockCount'] ?? null) === false
                && ($payload['saveAsStockCount'] ?? null) === true
                && ($payload['items'][0]['quantity'] ?? null) === 7
                && ($payload['items'][0]['gtin'] ?? null) === '7040000002001'
                && ($payload['items'][0]['externalSKU'] ?? null) === 'READY-1';
        });
        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/wp-json/'));
        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/api/products'));
        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/api/PricelistV2'));

        $item = $run->fresh()->items()->firstOrFail();
        $this->assertSame('synced', $item->stock_sync_status);
        $this->assertSame('POST /api/Stock/adjust', $item->stock_last_request_summary_json['endpoint']);
        $this->assertFalse($item->stock_last_request_summary_json['isCompleteStockCount']);
        $this->assertArrayNotHasKey('x-api-key', $item->stock_last_request_summary_json);
        $auditJson = AuditLog::query()->where('action', 'front_stock_sync')->get()->toJson();
        $this->assertStringNotContainsString('front-secret-key', $auditJson);
    }

    public function test_stock_sync_does_not_call_http_when_gates_fail(): void
    {
        Http::fake();
        [$user, $organization] = $this->userWithOrganization();
        $this->wooConnection($organization);
        $this->frontConnection($organization);
        $run = $this->runWithItems($organization, [
            [
                'sku' => 'READY-1',
                'gtin' => '7040000002001',
                'validation_status' => 'ready',
                'sync_status' => 'synced',
                'stock_quantity' => 7,
            ],
        ]);

        $this->actingAs($user)
            ->post('/product-sync/runs/' . $run->id . '/stock')
            ->assertRedirect();

        Http::assertNothingSent();
        $this->assertSame('not_applicable', $run->fresh()->items()->firstOrFail()->stock_sync_status);
    }

    public function test_stock_sync_marks_failed_front_response_and_retry_can_succeed(): void
    {
        Http::fake([
            'https://front.example.test/restapi/V2/api/Stock/adjust' => Http::sequence()
                ->push(['message' => 'Bad stock'], 422)
                ->push('Ok', 200),
        ]);
        [$user, $organization] = $this->userWithOrganization();
        $this->wooConnection($organization);
        $this->frontConnection($organization);
        app(ProductSyncProfileProvisioner::class)->ensureDefault($organization)
            ->update([
                'mode' => 'staging_batch',
                'stock_strategy' => 'stock_sync_later',
                'front_stock_ext_id' => 'EXT-2001',
            ]);
        $run = $this->runWithItems($organization, [
            [
                'sku' => 'READY-1',
                'gtin' => '7040000002001',
                'validation_status' => 'ready',
                'sync_status' => 'synced',
                'stock_quantity' => 7,
                'front_external_sku' => 'READY-1',
            ],
        ]);

        $this->actingAs($user)
            ->post('/product-sync/runs/' . $run->id . '/stock')
            ->assertRedirect();

        $item = $run->fresh()->items()->firstOrFail();
        $this->assertSame('failed', $item->stock_sync_status);
        $this->assertSame('HTTP 422', $item->stock_last_error);

        $this->actingAs($user)
            ->post('/product-sync/runs/' . $run->id . '/retry-stock')
            ->assertRedirect();

        $item = $run->fresh()->items()->firstOrFail();
        $this->assertSame('synced', $item->stock_sync_status);
        $this->assertSame(2, $item->stock_attempt_count);
        $this->assertSame('EXT-2001', $item->stock_last_request_summary_json['stockExtId']);
    }

    public function test_staging_batch_sync_updates_when_existing_product_mapping_exists(): void
    {
        Http::fake([
            'https://front.example.test/restapi/V2/api/products/front-existing-extid' => Http::response([
                'productid' => 333,
                'extId' => 'front-existing-extid',
                'productSizes' => [
                    ['identity' => 'identity-333', 'externalSKU' => 'READY-1'],
                ],
            ]),
        ]);
        [$user, $organization] = $this->userWithOrganization();
        $this->wooConnection($organization);
        $this->frontConnection($organization);
        app(ProductSyncProfileProvisioner::class)->ensureDefault($organization)
            ->update(['mode' => 'staging_batch']);
        $run = $this->runWithItems($organization, [
            ['sku' => 'READY-1', 'gtin' => '7040000002001', 'validation_status' => 'ready'],
        ]);
        ProductMapping::query()->create([
            'organization_id' => $organization->id,
            'woo_item_key' => 'product:1000',
            'woo_product_id' => 1000,
            'front_product_ext_id' => 'front-existing-extid',
            'sync_status' => 'synced',
        ]);

        $this->actingAs($user)
            ->post('/product-sync/runs/' . $run->id . '/staging-batch-sync')
            ->assertRedirect();

        Http::assertSent(function ($request): bool {
            return $request->method() === 'PUT'
                && (string) $request->url() === 'https://front.example.test/restapi/V2/api/products/front-existing-extid';
        });
        Http::assertSentCount(1);
        $item = $run->items()->firstOrFail();
        $this->assertSame('synced', $item->sync_status);
        $this->assertSame('PUT /api/products/{productId}', $item->last_request_summary_json['endpoint']);
        $this->assertSame('product_mapping', $item->last_request_summary_json['decision_source']);
    }

    public function test_staging_batch_sync_uses_stable_woo_id_mapping_when_sku_and_gtin_change(): void
    {
        Http::fake([
            'https://front.example.test/restapi/V2/api/products/front-existing-extid' => Http::response([
                'productid' => 333,
                'extId' => 'front-existing-extid',
                'productSizes' => [
                    [
                        'identity' => 'identity-333',
                        'gtin' => '7040000009999',
                        'externalSKU' => 'NEW-SKU',
                    ],
                ],
            ]),
        ]);
        [$user, $organization] = $this->userWithOrganization();
        $this->wooConnection($organization);
        $this->frontConnection($organization);
        app(ProductSyncProfileProvisioner::class)->ensureDefault($organization)
            ->update(['mode' => 'staging_batch']);
        $run = $this->runWithItems($organization, [
            ['sku' => 'NEW-SKU', 'gtin' => '7040000009999', 'validation_status' => 'ready'],
        ]);
        ProductMapping::query()->create([
            'organization_id' => $organization->id,
            'woo_item_key' => 'product:1000',
            'woo_product_id' => 1000,
            'front_product_ext_id' => 'front-existing-extid',
            'sku' => 'OLD-SKU',
            'gtin' => '7040000000001',
            'sync_status' => 'synced',
        ]);

        $this->actingAs($user)
            ->post('/product-sync/runs/' . $run->id . '/staging-batch-sync')
            ->assertRedirect();

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->method() === 'PUT'
                && (string) $request->url() === 'https://front.example.test/restapi/V2/api/products/front-existing-extid'
                && ($payload['number'] ?? null) === 'NEW-SKU'
                && ($payload['productSizes'][0]['externalSKU'] ?? null) === 'NEW-SKU'
                && ($payload['productSizes'][0]['gtin'] ?? null) === '7040000009999';
        });
        Http::assertSentCount(1);

        $mapping = ProductMapping::query()->where('woo_item_key', 'product:1000')->firstOrFail();

        $this->assertSame('front-existing-extid', $mapping->front_product_ext_id);
        $this->assertSame('NEW-SKU', $mapping->sku);
        $this->assertSame('7040000009999', $mapping->gtin);
        $this->assertSame('product_mapping', $run->items()->firstOrFail()->last_request_summary_json['decision_source']);
    }

    public function test_staging_batch_sync_updates_when_gtin_lookup_finds_existing_product(): void
    {
        Http::fake([
            'https://front.example.test/restapi/V2/api/products/woo-product-1000' => Http::response([], 404),
            'https://front.example.test/restapi/V2/api/Product/gtin/7040000002001' => Http::response([
                'productid' => 444,
                'extId' => 'front-gtin-extid',
                'productSizes' => [
                    ['identity' => 'identity-444', 'externalSKU' => 'READY-1'],
                ],
            ]),
            'https://front.example.test/restapi/V2/api/products/444' => Http::response([
                'productid' => 444,
                'extId' => 'front-gtin-extid',
                'productSizes' => [
                    ['identity' => 'identity-444', 'externalSKU' => 'READY-1'],
                ],
            ]),
        ]);
        [$user, $organization] = $this->userWithOrganization();
        $this->wooConnection($organization);
        $this->frontConnection($organization);
        app(ProductSyncProfileProvisioner::class)->ensureDefault($organization)
            ->update(['mode' => 'staging_batch']);
        $run = $this->runWithItems($organization, [
            ['sku' => 'READY-1', 'gtin' => '7040000002001', 'validation_status' => 'ready'],
        ]);

        $this->actingAs($user)
            ->post('/product-sync/runs/' . $run->id . '/staging-batch-sync')
            ->assertRedirect();

        Http::assertSent(function ($request): bool {
            return $request->method() === 'GET'
                && (string) $request->url() === 'https://front.example.test/restapi/V2/api/Product/gtin/7040000002001';
        });
        Http::assertSent(function ($request): bool {
            return $request->method() === 'PUT'
                && (string) $request->url() === 'https://front.example.test/restapi/V2/api/products/444';
        });
        $item = $run->items()->firstOrFail();
        $this->assertSame('synced', $item->sync_status);
        $this->assertSame('front_gtin_lookup', $item->last_request_summary_json['decision_source']);
    }

    public function test_staging_batch_retry_failed_items_resubmits_failed_items_only(): void
    {
        Http::fake([
            'https://front.example.test/restapi/V2/api/products/woo-product-1000' => Http::response([], 404),
            'https://front.example.test/restapi/V2/api/Product/gtin/7040000002001' => Http::response([], 404),
            'https://front.example.test/restapi/V2/api/products' => Http::response([
                'productid' => 555,
                'extId' => 'woo-product-1000',
                'productSizes' => [
                    ['identity' => 'identity-555', 'externalSKU' => 'READY-1'],
                ],
            ]),
        ]);
        [$user, $organization] = $this->userWithOrganization();
        $this->wooConnection($organization);
        $this->frontConnection($organization);
        app(ProductSyncProfileProvisioner::class)->ensureDefault($organization)
            ->update(['mode' => 'staging_batch']);
        $run = $this->runWithItems($organization, [
            ['sku' => 'READY-1', 'gtin' => '7040000002001', 'validation_status' => 'ready', 'sync_status' => 'failed'],
            ['sku' => 'READY-2', 'gtin' => '7040000002002', 'validation_status' => 'ready', 'sync_status' => 'synced'],
        ]);

        $this->actingAs($user)
            ->post('/product-sync/runs/' . $run->id . '/retry-failed')
            ->assertRedirect();

        $this->assertSame('synced', $run->items()->where('woo_sku', 'READY-1')->firstOrFail()->sync_status);
        $this->assertSame('synced', $run->items()->where('woo_sku', 'READY-2')->firstOrFail()->sync_status);
        Http::assertSentCount(3);
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
            ->assertSee('Review product readiness')
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

    private function frontConnection(Organization $organization): Connection
    {
        $connection = Connection::query()->create([
            'organization_id' => $organization->id,
            'type' => 'front_systems',
            'name' => 'Front staging',
            'base_url' => 'https://front.example.test/restapi/V2',
            'status' => 'success',
        ]);

        app(CredentialVault::class)->store($connection, 'api_key', [
            'value' => 'front-secret-key',
        ]);

        return $connection->fresh('credentials');
    }

    private function wooConnection(Organization $organization): Connection
    {
        return Connection::query()->create([
            'organization_id' => $organization->id,
            'type' => 'woocommerce',
            'name' => 'WooCommerce staging',
            'base_url' => 'https://woo.example.test',
            'status' => 'success',
        ]);
    }

    private function wooDiscoverySnapshot(Organization $organization): ConnectionDiscoverySnapshot
    {
        $connection = $organization->connections()->where('type', 'woocommerce')->first()
            ?? $this->wooConnection($organization);

        return ConnectionDiscoverySnapshot::query()->create([
            'organization_id' => $organization->id,
            'connection_id' => $connection->id,
            'source_system' => 'woocommerce',
            'discovery_type' => 'products',
            'status' => 'success',
            'summary_json' => [
                'count' => 1,
                'variation_count' => 1,
                'read_only' => true,
            ],
            'sample_json' => [
                'products' => [
                    [
                        'id' => 123,
                        'name' => 'Boot parent',
                        'type' => 'variable',
                        'sku' => 'BOOT-PARENT',
                        'regular_price' => '599',
                        'stock_status' => 'instock',
                        'manage_stock' => false,
                        'categories' => ['Shoes', 'Boots'],
                        'brands' => ['Brand candidate'],
                        'images' => [
                            ['src' => 'https://woo.example.test/boot.jpg', 'alt' => 'Boot'],
                        ],
                        'gtin_candidate' => [
                            'key' => null,
                            'value' => null,
                            'confidence' => 'none',
                            'candidates' => [],
                        ],
                    ],
                ],
                'variations' => [
                    [
                        'id' => 456,
                        'parent_id' => 123,
                        'name' => '24',
                        'sku' => 'BOOT-24',
                        'regular_price' => '599',
                        'stock_status' => 'instock',
                        'manage_stock' => true,
                        'discovery_status' => 'success',
                        'attributes' => [
                            ['name' => 'Size', 'option' => '24'],
                        ],
                        'gtin_candidate' => [
                            'key' => '_izettle_barcode',
                            'value' => '7040000000456',
                            'confidence' => 'exact_known_field',
                            'candidates' => [],
                        ],
                    ],
                ],
            ],
            'checked_at' => now(),
        ]);
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
            'sale_price_list_name' => 'WooCommerce Sale Prices',
            'stock_strategy' => 'do_not_sync_stock_yet',
            'front_stock_id' => null,
            'front_stock_ext_id' => null,
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
                'woo_stock_quantity' => $item['stock_quantity'] ?? null,
                'detected_gtin' => $item['gtin'],
                'front_match_status' => 'no_match',
                'front_product_ext_id' => $item['front_product_ext_id'] ?? null,
                'front_product_id' => $item['front_product_id'] ?? null,
                'front_external_sku' => $item['front_external_sku'] ?? null,
                'proposed_front_payload_json' => [
                    'name' => 'Product ' . ($index + 1),
                    'number' => $item['sku'],
                    'variant' => $item['sku'],
                    'brand' => 'Brand candidate',
                    'groupName' => 'Shoes',
                    'subgroupName' => 'Boots',
                    'description' => 'Full product description for store staff.',
                    'internalDescription' => 'Short product note.',
                    'tags' => 'summer, staff-pick',
                    'price_candidate' => '599',
                    'sale_price_candidate' => $item['sale_price'] ?? null,
                    'image_candidate' => [
                        'src' => 'https://example.test/image-' . ($index + 1) . '.jpg',
                        'alt' => 'Product image',
                    ],
                    'image_candidates' => [
                        [
                            'src' => 'https://example.test/image-' . ($index + 1) . '.jpg',
                            'alt' => 'Product image',
                        ],
                        [
                            'src' => 'https://example.test/image-' . ($index + 1) . '-back.jpg',
                            'alt' => 'Product image back',
                        ],
                    ],
                    'productSizes' => [
                        [
                            'label' => '24',
                            'gtin' => $item['gtin'],
                            'externalSKU' => $item['sku'],
                        ],
                    ],
                ],
                'validation_status' => $item['validation_status'] ?? 'warning',
                'sync_status' => $item['sync_status'] ?? 'not_started',
                'sale_price_sync_status' => $item['sale_price_sync_status'] ?? 'not_applicable',
                'stock_sync_status' => $item['stock_sync_status'] ?? 'not_applicable',
                'validation_errors_json' => [],
                'validation_warnings_json' => [],
            ]);
        }

        return $run;
    }
}
