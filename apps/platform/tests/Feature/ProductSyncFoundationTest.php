<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ProductMapping;
use App\Models\ProductSyncPreviewPlan;
use App\Models\ProductSyncProfile;
use App\Models\ProductSyncRun;
use App\Models\User;
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
            'sync_only_opted_in_products' => true,
            'include_variable_products' => false,
            'include_variations' => false,
        ]);
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
        $this->assertSame('draft', $run->status);
        $this->assertSame('preview_only', $run->mode);
        $this->assertSame(1, $run->total_candidates);
        $this->assertSame(1, $run->total_ready);
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

    public function test_validation_blocks_missing_sku_and_gtin_when_required(): void
    {
        [$user, $organization] = $this->userWithOrganization();
        app(ProductSyncProfileProvisioner::class)->ensureDefault($organization);
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

    public function test_variable_products_are_blocked_by_default(): void
    {
        [$user, $organization] = $this->userWithOrganization();
        app(ProductSyncProfileProvisioner::class)->ensureDefault($organization);
        $this->previewPlan($organization, [
            $this->planRow(123, 'BOOT-24', '7040000000012', 'variable'),
        ]);

        $this->actingAs($user)
            ->post('/product-sync/preview-run')
            ->assertRedirect();

        $item = ProductSyncRun::query()->firstOrFail()->items()->firstOrFail();

        $this->assertSame('blocked', $item->validation_status);
        $this->assertContains('Variable products are disabled in this sync profile.', $item->validation_errors_json);
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
            ->assertDontSee('API body');
    }

    public function test_advanced_page_is_separate(): void
    {
        [$user] = $this->userWithOrganization();

        $this->actingAs($user)
            ->get('/advanced')
            ->assertOk()
            ->assertSee('Technical settings')
            ->assertSee('Webhooks')
            ->assertSee('API Settings');
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

    private function planRow(int $id, ?string $sku, ?string $gtin, string $type = 'simple'): array
    {
        return [
            'woo_product' => [
                'id' => $id,
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
            'max_products_per_run' => 100,
            'sync_only_opted_in_products' => '1',
            'include_simple_products' => '1',
            'require_sku' => '1',
            'require_gtin' => '1',
            'require_price' => '1',
            'max_products_per_batch' => 25,
            'woo_query_limit' => 100,
            'front_write_limit' => 25,
            'price_strategy' => 'regular_price_only',
            'stock_strategy' => 'do_not_sync_stock_yet',
        ];
    }
}
