<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\ConnectionDiscoverySnapshot;
use App\Models\Organization;
use App\Models\ProductMapping;
use App\Models\ProductSyncPreviewPlan;
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
            ->post('/mapping/product-poc/plan', ['woo_product_ids' => [123]])
            ->assertSessionHasErrors('woo_product_ids');

        $this->assertSame(0, ProductSyncPreviewPlan::query()->count());
    }

    public function test_cannot_create_plan_without_front_discovery_snapshot(): void
    {
        [$user, $organization] = $this->userWithOrganization();
        $wooConnection = $this->connection($organization, 'woocommerce');
        $this->snapshot($organization, $wooConnection, 'woocommerce', [$this->wooProduct()]);

        $this->actingAs($user)
            ->post('/mapping/product-poc/plan', ['woo_product_ids' => [123]])
            ->assertSessionHasErrors('woo_product_ids');

        $this->assertSame(0, ProductSyncPreviewPlan::query()->count());
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
            ->post('/mapping/product-poc/plan', ['woo_product_ids' => range(1, 11)])
            ->assertSessionHasErrors('woo_product_ids');

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
        ], [123, 124]);

        $this->assertSame('blocked', $plan->status);
        $this->assertContains('Duplicate GTIN/EAN within selected sample.', $plan->plan_json['rows'][0]['blocks']);
        $this->assertContains('Duplicate GTIN/EAN within selected sample.', $plan->plan_json['rows'][1]['blocks']);
    }

    public function test_duplicate_sku_is_blocked(): void
    {
        $plan = $this->planForProducts([
            $this->wooProduct(id: 123, sku: 'BOOT-24', gtin: '7040000000012'),
            $this->wooProduct(id: 124, sku: 'BOOT-24', gtin: '7040000000013'),
        ], [123, 124]);

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

    public function test_variable_product_is_blocked_for_now(): void
    {
        $plan = $this->planForProducts([
            $this->wooProduct(type: 'variable'),
        ]);

        $this->assertSame('blocked', $plan->status);
        $this->assertContains('Variable product: variations are not fetched yet.', $plan->plan_json['rows'][0]['blocks']);
    }

    public function test_gtin_match_beats_sku_match(): void
    {
        $plan = $this->planForProducts([
            $this->wooProduct(sku: 'SAME-SKU', gtin: 'GTIN-WINS'),
        ], [123], [
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
        ], [123], [
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
            ->post('/mapping/product-poc/plan', ['woo_product_ids' => [123]])
            ->assertRedirect('/mapping/product-poc');

        $this->actingAs($user)
            ->get('/mapping/product-poc')
            ->assertOk()
            ->assertSee('10-Product Mapping PoC')
            ->assertSee('Preview only')
            ->assertSee('Woo Boot')
            ->assertSee('Generate 10-product sync plan')
            ->assertSee('Generated Plan')
            ->assertSee('NEEDS_CONFIRMATION');
    }

    private function planForProducts(array $wooProducts, array $selectedIds = [123], ?array $frontProducts = null): ProductSyncPreviewPlan
    {
        [$user, $organization] = $this->userWithOrganization();
        $wooConnection = $this->connection($organization, 'woocommerce');
        $frontConnection = $this->connection($organization, 'front_systems');

        $this->snapshot($organization, $wooConnection, 'woocommerce', $wooProducts);
        $this->snapshot($organization, $frontConnection, 'front_systems', $frontProducts ?? [$this->frontProduct()]);

        $this->actingAs($user)
            ->post('/mapping/product-poc/plan', ['woo_product_ids' => $selectedIds])
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

    private function snapshot(Organization $organization, Connection $connection, string $source, array $products): ConnectionDiscoverySnapshot
    {
        return ConnectionDiscoverySnapshot::query()->create([
            'organization_id' => $organization->id,
            'connection_id' => $connection->id,
            'source_system' => $source,
            'discovery_type' => 'products',
            'status' => 'success',
            'summary_json' => ['count' => count($products), 'read_only' => true],
            'sample_json' => ['products' => $products],
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
