<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\ConnectionDiscoverySnapshot;
use App\Models\Organization;
use App\Models\ProductMapping;
use App\Models\ProductSyncPreviewPlan;
use App\Models\ProductSyncRunItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductMappingPocTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_requires_authentication(): void
    {
        $this->get('/mapping/product-poc')
            ->assertRedirect('/login');
    }

    public function test_cannot_create_plan_without_woo_discovery_snapshot(): void
    {
        [$user] = $this->userWithOrganization();

        $this->actingAs($user)
            ->post('/mapping/product-poc/plan', ['woo_item_keys' => ['product:123']])
            ->assertSessionHasErrors('woo_item_keys');

        $this->assertSame(0, ProductSyncPreviewPlan::query()->count());
    }

    public function test_can_create_woo_only_plan_without_front_discovery_snapshot(): void
    {
        [$user, $organization] = $this->userWithOrganization();
        $wooConnection = $this->connection($organization, 'woocommerce');
        $this->snapshot($organization, $wooConnection, 'woocommerce', [$this->wooProduct()]);

        $this->actingAs($user)
            ->post('/mapping/product-poc/plan', ['woo_item_keys' => ['product:123']])
            ->assertRedirect('/mapping/product-poc');

        $plan = ProductSyncPreviewPlan::query()->firstOrFail();

        $this->assertNull($plan->front_connection_id);
        $this->assertFalse($plan->summary_json['front_sample_available']);
        $this->assertSame('front_sample_missing', $plan->plan_json['rows'][0]['front_match']['status']);
        $this->assertContains('Front product sample is missing; existing Front match could not be checked.', $plan->plan_json['rows'][0]['warnings']);
    }

    public function test_max_ten_products_is_enforced(): void
    {
        [$user, $organization] = $this->userWithOrganization();
        $wooConnection = $this->connection($organization, 'woocommerce');
        $frontConnection = $this->connection($organization, 'front_systems');

        $products = collect(range(1, 11))
            ->map(fn (int $index): array => $this->wooProduct(id: $index, sku: "SKU-{$index}", gtin: "70400000000{$index}"))
            ->all();

        $this->snapshot($organization, $wooConnection, 'woocommerce', $products);
        $this->snapshot($organization, $frontConnection, 'front_systems', [$this->frontProduct()]);

        $this->actingAs($user)
            ->post('/mapping/product-poc/plan', ['woo_item_keys' => collect(range(1, 11))->map(fn (int $id): string => "product:{$id}")->all()])
            ->assertSessionHasErrors('woo_item_keys');

        $this->assertSame(0, ProductSyncPreviewPlan::query()->count());
    }

    public function test_product_missing_sku_is_blocked(): void
    {
        $plan = $this->planForProducts([
            $this->wooProduct(sku: ''),
        ]);

        $this->assertSame('blocked', $plan->status);
        $this->assertContains('Missing SKU.', $plan->plan_json['rows'][0]['blocks']);
    }

    public function test_product_missing_gtin_is_blocked(): void
    {
        $plan = $this->planForProducts([
            $this->wooProduct(gtin: ''),
        ]);

        $this->assertSame('blocked', $plan->status);
        $this->assertContains('Missing GTIN/EAN candidate.', $plan->plan_json['rows'][0]['blocks']);
    }

    public function test_duplicate_gtin_is_blocked(): void
    {
        $plan = $this->planForProducts([
            $this->wooProduct(id: 123, sku: 'BOOT-24', gtin: '7040000000012'),
            $this->wooProduct(id: 124, sku: 'BOOT-25', gtin: '7040000000012'),
        ], ['product:123', 'product:124']);

        $this->assertSame('blocked', $plan->status);
        $this->assertContains('Duplicate GTIN/EAN within selected sample.', $plan->plan_json['rows'][0]['blocks']);
        $this->assertContains('Duplicate GTIN/EAN within selected sample.', $plan->plan_json['rows'][1]['blocks']);
    }

    public function test_duplicate_sku_is_blocked(): void
    {
        $plan = $this->planForProducts([
            $this->wooProduct(id: 123, sku: 'BOOT-24', gtin: '7040000000012'),
            $this->wooProduct(id: 124, sku: 'BOOT-24', gtin: '7040000000013'),
        ], ['product:123', 'product:124']);

        $this->assertSame('blocked', $plan->status);
        $this->assertContains('Duplicate SKU within selected sample.', $plan->plan_json['rows'][0]['blocks']);
        $this->assertContains('Duplicate SKU within selected sample.', $plan->plan_json['rows'][1]['blocks']);
    }

    public function test_product_missing_price_is_blocked(): void
    {
        $product = $this->wooProduct();
        $product['price'] = '';
        $product['regular_price'] = '';

        $plan = $this->planForProducts([$product]);

        $this->assertSame('blocked', $plan->status);
        $this->assertContains('No price candidate exists.', $plan->plan_json['rows'][0]['blocks']);
    }

    public function test_variable_parent_warns_that_variations_are_usually_sellable_candidates(): void
    {
        $plan = $this->planForProducts([
            $this->wooProduct(type: 'variable', gtin: '7040000000012'),
        ]);

        $this->assertSame('ready', $plan->status);
        $this->assertContains('Variable parent selected. Usually the sellable variation rows should be selected instead.', $plan->plan_json['rows'][0]['warnings']);
    }

    public function test_gtin_match_beats_sku_match(): void
    {
        $plan = $this->planForProducts([
            $this->wooProduct(sku: 'SAME-SKU', gtin: 'GTIN-WINS'),
        ], ['product:123'], [
            $this->frontProduct(productId: 501, name: 'GTIN Match', gtin: 'GTIN-WINS', externalSku: 'OTHER-SKU', identity: 'OTHER-ID'),
            $this->frontProduct(productId: 502, name: 'SKU Match', gtin: 'OTHER-GTIN', externalSku: 'SAME-SKU', identity: 'SAME-SKU'),
        ]);

        $match = $plan->plan_json['rows'][0]['front_match'];

        $this->assertSame('matched_existing_front_product', $match['status']);
        $this->assertSame(501, $match['productid']);
        $this->assertSame('gtin', $match['method']);
        $this->assertSame('high', $match['confidence']);
    }

    public function test_sku_external_sku_fallback_works(): void
    {
        $plan = $this->planForProducts([
            $this->wooProduct(sku: 'SKU-FALLBACK', gtin: 'NO-GTIN-MATCH'),
        ], ['product:123'], [
            $this->frontProduct(productId: 501, name: 'SKU Match', gtin: null, externalSku: 'SKU-FALLBACK', identity: 'OTHER-ID'),
        ]);

        $match = $plan->plan_json['rows'][0]['front_match'];

        $this->assertSame('matched_existing_front_product', $match['status']);
        $this->assertSame(501, $match['productid']);
        $this->assertSame('sku_external_sku', $match['method']);
    }

    public function test_preview_plan_does_not_write_final_product_mappings_or_call_external_apis(): void
    {
        Http::fake();

        $plan = $this->planForProducts([
            $this->wooProduct(),
        ]);

        $this->assertNotNull($plan->id);
        $this->assertSame(0, ProductMapping::query()->count());
        Http::assertNothingSent();
    }

    public function test_no_front_or_woo_write_endpoints_are_called(): void
    {
        Http::fake();

        $this->planForProducts([
            $this->wooProduct(),
        ]);

        Http::assertNotSent(fn ($request): bool => in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            || str_contains($request->url(), '/api/products')
            || str_contains($request->url(), '/api/PricelistV2')
            || str_contains($request->url(), '/api/Stock/adjust')
            || str_contains($request->url(), '/api/Sale')
            || str_contains($request->url(), '/api/OmniChannel')
            || str_contains($request->url(), '/wp-json/wc/v3/products/'));
    }

    public function test_page_shows_selection_and_generated_plan(): void
    {
        [$user, $organization] = $this->userWithOrganization();
        $wooConnection = $this->connection($organization, 'woocommerce');
        $frontConnection = $this->connection($organization, 'front_systems');
        $this->snapshot($organization, $wooConnection, 'woocommerce', [$this->wooProduct()]);
        $this->snapshot($organization, $frontConnection, 'front_systems', [$this->frontProduct()]);

        $this->actingAs($user)
            ->post('/mapping/product-poc/plan', ['woo_item_keys' => ['product:123']])
            ->assertRedirect('/mapping/product-poc');

        $this->actingAs($user)
            ->get('/mapping/product-poc')
            ->assertOk()
            ->assertSee('Mapping Preview Lab')
            ->assertSee('Preview only')
            ->assertSee('Select Products and Variations')
            ->assertSee('Woo Boot')
            ->assertSee('Generate 10-product sync plan')
            ->assertSee('Generated Plan')
            ->assertSee('NEEDS_CONFIRMATION');
    }

    public function test_page_shows_woo_candidates_when_front_snapshot_is_missing(): void
    {
        [$user, $organization] = $this->userWithOrganization();
        $wooConnection = $this->connection($organization, 'woocommerce');
        $this->snapshot($organization, $wooConnection, 'woocommerce', [
            $this->wooProduct(id: 123, sku: 'PARENT-SKU', gtin: null, type: 'variable'),
        ], [$this->wooVariation()]);

        $this->actingAs($user)
            ->get('/mapping/product-poc')
            ->assertOk()
            ->assertSee('You can still create a Woo-only readiness plan')
            ->assertSee('variation:456')
            ->assertSee('BOOT-24-BLUE')
            ->assertSee('front_sample_missing')
            ->assertSee('Run Front product discovery later to check for existing matches.');
    }

    public function test_variation_from_discovery_snapshot_can_be_selected_as_first_class_candidate(): void
    {
        [$user, $organization] = $this->userWithOrganization();
        $wooConnection = $this->connection($organization, 'woocommerce');
        $frontConnection = $this->connection($organization, 'front_systems');
        $variation = $this->wooVariation();

        $this->snapshot($organization, $wooConnection, 'woocommerce', [
            $this->wooProduct(id: 123, sku: 'PARENT-SKU', gtin: null, type: 'variable'),
        ], [$variation]);
        $this->snapshot($organization, $frontConnection, 'front_systems', [
            $this->frontProduct(gtin: '7040000000456', externalSku: 'BOOT-24-BLUE', identity: 'BOOT-24-BLUE'),
        ]);

        $this->actingAs($user)
            ->post('/mapping/product-poc/plan', ['woo_item_keys' => ['variation:456']])
            ->assertRedirect('/mapping/product-poc');

        $plan = ProductSyncPreviewPlan::query()->firstOrFail();
        $row = $plan->plan_json['rows'][0];

        $this->assertSame('ready', $plan->status);
        $this->assertSame('variation:456', $row['woo_product']['item_key']);
        $this->assertSame(123, $row['woo_product']['parent_product_id']);
        $this->assertSame('variation', $row['woo_product']['type']);
        $this->assertSame('7040000000456', $row['proposed_front_payload']['productSizes'][0]['gtin']);
        $this->assertSame('BOOT-24-BLUE', $row['proposed_front_payload']['productSizes'][0]['externalSKU']);
        $this->assertSame('matched_existing_front_product', $row['front_match']['status']);
        $this->assertSame('gtin', $row['front_match']['method']);

        $this->actingAs($user)
            ->post('/product-sync/preview-run')
            ->assertRedirect();

        $runItem = ProductSyncRunItem::query()->firstOrFail();

        $this->assertSame('variation:456', $runItem->woo_item_key);
        $this->assertSame(123, $runItem->woo_product_id);
        $this->assertSame(456, $runItem->woo_variation_id);
        $this->assertSame('BOOT-24-BLUE', $runItem->woo_sku);
        $this->assertSame('7040000000456', $runItem->detected_gtin);
    }

    private function planForProducts(array $wooProducts, array $selectedKeys = ['product:123'], ?array $frontProducts = null): ProductSyncPreviewPlan
    {
        [$user, $organization] = $this->userWithOrganization();
        $wooConnection = $this->connection($organization, 'woocommerce');
        $frontConnection = $this->connection($organization, 'front_systems');

        $this->snapshot($organization, $wooConnection, 'woocommerce', $wooProducts);
        $this->snapshot($organization, $frontConnection, 'front_systems', $frontProducts ?? [$this->frontProduct()]);

        $this->actingAs($user)
            ->post('/mapping/product-poc/plan', ['woo_item_keys' => $selectedKeys])
            ->assertRedirect('/mapping/product-poc');

        return ProductSyncPreviewPlan::query()->firstOrFail();
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

    private function connection(Organization $organization, string $type): Connection
    {
        return Connection::query()->create([
            'organization_id' => $organization->id,
            'type' => $type,
            'name' => "{$type} staging",
            'base_url' => $type === 'woocommerce' ? 'https://woo.example.test' : 'https://front.example.test/restapi/V2',
            'status' => 'pending',
        ]);
    }

    private function snapshot(Organization $organization, Connection $connection, string $source, array $products, array $variations = []): ConnectionDiscoverySnapshot
    {
        return ConnectionDiscoverySnapshot::query()->create([
            'organization_id' => $organization->id,
            'connection_id' => $connection->id,
            'source_system' => $source,
            'discovery_type' => 'products',
            'status' => 'success',
            'summary_json' => ['count' => count($products), 'read_only' => true],
            'sample_json' => ['products' => $products, 'variations' => $variations],
            'checked_at' => now(),
        ]);
    }

    private function wooProduct(
        int $id = 123,
        ?string $name = 'Woo Boot',
        ?string $sku = 'BOOT-24',
        ?string $gtin = '7040000000012',
        string $type = 'simple',
    ): array {
        return [
            'id' => $id,
            'name' => $name,
            'sku' => $sku,
            'type' => $type,
            'status' => 'publish',
            'price' => '499',
            'regular_price' => '599',
            'sale_price' => '499',
            'stock_quantity' => 3,
            'stock_status' => 'instock',
            'manage_stock' => true,
            'categories' => ['Shoes', 'Boots'],
            'brands' => ['Brand A'],
            'gtin_candidate' => [
                'key' => $gtin === null || $gtin === '' ? null : 'Zettle_barcode',
                'value' => $gtin,
                'confidence' => $gtin === null || $gtin === '' ? 'none' : 'exact_known_field',
                'candidates' => $gtin === null || $gtin === '' ? [] : [
                    ['key' => 'Zettle_barcode', 'value' => $gtin, 'confidence' => 'exact_known_field'],
                ],
            ],
        ];
    }

    private function wooVariation(
        int $id = 456,
        int $parentId = 123,
        ?string $name = 'Woo Boot Blue',
        ?string $sku = 'BOOT-24-BLUE',
        ?string $gtin = '7040000000456',
    ): array {
        return [
            'id' => $id,
            'parent_id' => $parentId,
            'parent_name' => 'Woo Boot',
            'name' => $name,
            'sku' => $sku,
            'type' => 'variation',
            'status' => 'publish',
            'price' => '599',
            'regular_price' => '599',
            'sale_price' => '',
            'stock_quantity' => 2,
            'stock_status' => 'instock',
            'manage_stock' => true,
            'attributes' => ['Blue', '24'],
            'gtin_candidate' => [
                'key' => $gtin === null || $gtin === '' ? null : '_izettle_barcode',
                'value' => $gtin,
                'confidence' => $gtin === null || $gtin === '' ? 'none' : 'exact_known_field',
                'candidates' => $gtin === null || $gtin === '' ? [] : [
                    ['key' => '_izettle_barcode', 'value' => $gtin, 'confidence' => 'exact_known_field'],
                ],
            ],
            'discovery_status' => 'success',
        ];
    }

    private function frontProduct(
        int $productId = 501,
        string $name = 'Front Boot',
        ?string $gtin = '7040000000012',
        ?string $externalSku = 'BOOT-24',
        ?string $identity = 'IDENT-24',
    ): array {
        return [
            'productid' => $productId,
            'name' => $name,
            'brand' => 'Brand A',
            'groupName' => 'Shoes',
            'subgroupName' => 'Boots',
            'productSizes' => [
                [
                    'gtin' => $gtin,
                    'identity' => $identity,
                    'externalSKU' => $externalSku,
                    'label' => '24',
                ],
            ],
        ];
    }
}
