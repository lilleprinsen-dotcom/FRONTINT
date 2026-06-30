<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\ConnectionDiscoverySnapshot;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Services\Credentials\CredentialVault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConnectionDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_discovery_is_skipped_without_http_calls_when_live_checks_are_disabled(): void
    {
        config(['omnibridge.allow_connection_test_http' => false]);
        Http::fake();

        [$user, $connection] = $this->connectionWithCredentials('woocommerce', [
            'consumer_key' => 'ck_test',
            'consumer_secret' => 'cs_test',
        ]);

        $this->actingAs($user)
            ->postJson("/connections/{$connection->id}/discover/products")
            ->assertOk()
            ->assertJson([
                'status' => 'skipped',
                'discovery_type' => 'products',
            ]);

        Http::assertNothingSent();

        $snapshot = ConnectionDiscoverySnapshot::query()->first();
        $this->assertSame('skipped', $snapshot->status);
        $this->assertSame('products', $snapshot->discovery_type);
        $this->assertNotNull($snapshot->checked_at);
    }

    public function test_front_stores_discovery_uses_read_only_stores_endpoint(): void
    {
        config(['omnibridge.allow_connection_test_http' => true]);

        Http::fake([
            'https://front.example.test/restapi/V2/api/Stores' => Http::response([
                [
                    'StoreId' => 1001,
                    'StoreNo' => 'OSLO',
                    'StoreName' => 'Lilleprinsen Test',
                    'StockId' => 2001,
                    'ExternalStockId' => 'EXT-2001',
                    'Currency' => 'NOK',
                    'TimeZoneInfo' => 'Europe/Oslo',
                    'Email' => 'private@example.test',
                ],
            ]),
        ]);

        [$user, $connection] = $this->connectionWithCredentials('front_systems', [
            'api_key' => 'front-secret-key',
        ], 'https://front.example.test/restapi/V2');

        $this->actingAs($user)
            ->postJson("/connections/{$connection->id}/discover/stores")
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'discovery_type' => 'stores',
            ]);

        Http::assertSent(fn ($request): bool => $request->method() === 'GET'
            && $request->url() === 'https://front.example.test/restapi/V2/api/Stores'
            && $request->hasHeader('x-api-key', 'front-secret-key'));

        $snapshot = ConnectionDiscoverySnapshot::query()->firstOrFail();
        $this->assertSame('Lilleprinsen Test', $snapshot->sample_json['stores'][0]['store_name']);
        $this->assertSame('EXT-2001', $snapshot->sample_json['stores'][0]['external_stock_id']);
        $this->assertStringNotContainsString('private@example.test', json_encode($snapshot->sample_json));
        $this->assertStringNotContainsString('front-secret-key', json_encode($snapshot->toArray()));

        $auditLog = AuditLog::query()->where('action', 'live_readonly_discovery_stores')->firstOrFail();
        $this->assertSame($user->id, $auditLog->user_id);
        $this->assertSame($connection->id, $auditLog->metadata_json['connection_id']);
        $this->assertSame('GET /api/Stores', $auditLog->metadata_json['endpoint_group']);
        $this->assertSame('success', $auditLog->metadata_json['status']);
        $this->assertTrue($auditLog->metadata_json['live_http_enabled']);
        $this->assertTrue($auditLog->metadata_json['production_writes_disabled']);
        $this->assertStringNotContainsString('front-secret-key', json_encode($auditLog->metadata_json));
    }

    public function test_front_products_discovery_uses_product_search_with_small_limit(): void
    {
        config(['omnibridge.allow_connection_test_http' => true]);

        Http::fake([
            'https://front.example.test/restapi/V2/api/Product' => Http::response([
                [
                    'ProductId' => 501,
                    'Name' => 'Front Boot',
                    'Brand' => 'Brand A',
                    'GroupName' => 'Shoes',
                    'SubgroupName' => 'Boots',
                    'ProductSizes' => [
                        [
                            'Identity' => 'FRONT-BOOT-24',
                            'GTIN' => '7040000000012',
                            'Label' => '24',
                            'ExternalSKU' => 'BOOT-24',
                            'Identifiers' => ['ALT-24'],
                        ],
                    ],
                    'CostPrice' => 'do-not-store',
                ],
            ]),
        ]);

        [$user, $connection] = $this->connectionWithCredentials('front_systems', [
            'api_key' => 'front-secret-key',
        ], 'https://front.example.test/restapi/V2');

        $this->actingAs($user)
            ->postJson("/connections/{$connection->id}/discover/products")
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'discovery_type' => 'products',
            ]);

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && $request->url() === 'https://front.example.test/restapi/V2/api/Product'
            && $request['pageSize'] === 10
            && $request['pageSkip'] === 0
            && $request['includeStockQuantity'] === false);

        Http::assertNotSent(fn ($request): bool => in_array($request->method(), ['PUT', 'PATCH', 'DELETE'], true));
        Http::assertNotSent(fn ($request): bool => $request->method() === 'POST'
            && $request->url() !== 'https://front.example.test/restapi/V2/api/Product');
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/api/products'));

        $snapshot = ConnectionDiscoverySnapshot::query()->firstOrFail();
        $this->assertSame('7040000000012', $snapshot->sample_json['products'][0]['productSizes'][0]['gtin']);
        $this->assertStringNotContainsString('do-not-store', json_encode($snapshot->sample_json));
        $this->assertStringNotContainsString('front-secret-key', json_encode($snapshot->toArray()));
        $this->assertSame(10, $snapshot->summary_json['limit']);
        $this->assertStringContainsString('Read-only product listing endpoint', $snapshot->summary_json['front_openapi_note']);
    }

    public function test_front_product_discovery_limit_cannot_be_overridden_by_request_parameters(): void
    {
        config(['omnibridge.allow_connection_test_http' => true]);

        Http::fake([
            'https://front.example.test/restapi/V2/api/Product' => Http::response([]),
        ]);

        [$user, $connection] = $this->connectionWithCredentials('front_systems', [
            'api_key' => 'front-secret-key',
        ], 'https://front.example.test/restapi/V2');

        $this->actingAs($user)
            ->postJson("/connections/{$connection->id}/discover/products?limit=500&pageSize=500")
            ->assertOk()
            ->assertJson(['status' => 'success']);

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && $request->url() === 'https://front.example.test/restapi/V2/api/Product'
            && $request['pageSize'] === 10);
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/api/products'));
    }

    public function test_woocommerce_products_discovery_uses_read_only_product_endpoint_and_detects_gtin(): void
    {
        config(['omnibridge.allow_connection_test_http' => true]);

        Http::fake([
            'https://woo.example.test/wp-json/wc/v3/products*' => Http::response([
                [
                    'id' => 123,
                    'name' => 'Woo Boot',
                    'sku' => 'BOOT-24',
                    'type' => 'simple',
                    'status' => 'publish',
                    'permalink' => 'https://woo.example.test/product/woo-boot',
                    'price' => '499',
                    'regular_price' => '599',
                    'sale_price' => '499',
                    'stock_quantity' => 3,
                    'stock_status' => 'instock',
                    'manage_stock' => true,
                    'categories' => [['name' => 'Shoes']],
                    'images' => [
                        ['src' => 'https://woo.example.test/image.jpg', 'alt' => 'Woo Boot image'],
                    ],
                    'meta_data' => [
                        ['key' => 'Zettle_barcode', 'value' => '7040000000012'],
                        ['key' => 'private_api_key', 'value' => 'do-not-store'],
                    ],
                    'description' => '<p>Do not store this HTML.</p>',
                ],
            ]),
        ]);

        [$user, $connection] = $this->connectionWithCredentials('woocommerce', [
            'consumer_key' => 'ck_secret',
            'consumer_secret' => 'cs_secret',
        ]);

        $this->actingAs($user)
            ->postJson("/connections/{$connection->id}/discover/products")
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'discovery_type' => 'products',
            ]);

        Http::assertSent(fn ($request): bool => $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://woo.example.test/wp-json/wc/v3/products')
            && str_contains($request->url(), 'per_page=10')
            && str_contains($request->url(), 'page=1')
            && str_contains($request->url(), 'status=publish'));

        Http::assertNotSent(fn ($request): bool => in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true));

        $snapshot = ConnectionDiscoverySnapshot::query()->firstOrFail();
        $product = $snapshot->sample_json['products'][0];

        $this->assertSame('Zettle_barcode', $product['gtin_candidate']['key']);
        $this->assertSame('7040000000012', $product['gtin_candidate']['value']);
        $this->assertSame('exact_known_field', $product['gtin_candidate']['confidence']);
        $this->assertSame('https://woo.example.test/image.jpg', $product['image']['src']);
        $this->assertStringNotContainsString('do-not-store', json_encode($snapshot->sample_json));
        $this->assertStringNotContainsString('cs_secret', json_encode($snapshot->toArray()));
    }

    public function test_woocommerce_variation_discovery_uses_read_only_variations_endpoint_and_builds_readiness_report(): void
    {
        config(['omnibridge.allow_connection_test_http' => true]);

        Http::fake([
            'https://woo.example.test/wp-json/wc/v3/products/456/variations*' => Http::response([
                [
                    'id' => 9001,
                    'name' => 'Woo Shirt - 92',
                    'sku' => 'SHIRT-92',
                    'status' => 'publish',
                    'price' => '299',
                    'regular_price' => '299',
                    'sale_price' => '',
                    'stock_quantity' => 4,
                    'stock_status' => 'instock',
                    'manage_stock' => true,
                    'attributes' => [
                        ['name' => 'Size', 'option' => '92'],
                    ],
                    'meta_data' => [
                        ['key' => '_izettle_barcode', 'value' => '7040000000099'],
                    ],
                    'description' => '<p>Do not store variation HTML.</p>',
                ],
                [
                    'id' => 9002,
                    'name' => 'Woo Shirt - 98',
                    'sku' => '',
                    'status' => 'publish',
                    'price' => '',
                    'regular_price' => '',
                    'stock_quantity' => 0,
                    'stock_status' => 'outofstock',
                    'manage_stock' => false,
                    'attributes' => [
                        ['name' => 'Size', 'option' => '98'],
                    ],
                    'meta_data' => [],
                ],
            ]),
            'https://woo.example.test/wp-json/wc/v3/products*' => Http::response([
                [
                    'id' => 456,
                    'name' => 'Woo Shirt',
                    'sku' => 'SHIRT',
                    'type' => 'variable',
                    'status' => 'publish',
                    'price' => '',
                    'regular_price' => '',
                    'stock_quantity' => null,
                    'stock_status' => 'instock',
                    'manage_stock' => false,
                    'variations' => [9001, 9002],
                    'meta_data' => [],
                ],
            ]),
        ]);

        [$user, $connection] = $this->connectionWithCredentials('woocommerce', [
            'consumer_key' => 'ck_secret',
            'consumer_secret' => 'cs_secret',
        ]);

        $this->actingAs($user)
            ->postJson("/connections/{$connection->id}/discover/products")
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'discovery_type' => 'products',
            ]);

        Http::assertSent(fn ($request): bool => $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://woo.example.test/wp-json/wc/v3/products/456/variations')
            && str_contains($request->url(), 'per_page=10')
            && str_contains($request->url(), 'page=1'));

        Http::assertNotSent(fn ($request): bool => in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true));

        $snapshot = ConnectionDiscoverySnapshot::query()->firstOrFail();

        $this->assertSame(1, $snapshot->summary_json['count']);
        $this->assertSame(2, $snapshot->summary_json['variation_count']);
        $this->assertSame('GET /wp-json/wc/v3/products/{productId}/variations', $snapshot->summary_json['variation_endpoint']);
        $this->assertSame(9001, $snapshot->sample_json['variations'][0]['id']);
        $this->assertSame('7040000000099', $snapshot->sample_json['variations'][0]['gtin_candidate']['value']);
        $this->assertStringNotContainsString('Do not store variation HTML', json_encode($snapshot->sample_json));
        $this->assertStringNotContainsString('cs_secret', json_encode($snapshot->toArray()));

        $readiness = $snapshot->sample_json['readiness'];
        $this->assertSame(1, $readiness['summary']['ready']);
        $this->assertSame(2, $readiness['summary']['blocked']);

        $readyVariation = collect($readiness['rows'])->firstWhere('woo_variation_id', 9001);
        $blockedVariation = collect($readiness['rows'])->firstWhere('woo_variation_id', 9002);

        $this->assertSame('ready', $readyVariation['status']);
        $this->assertSame('blocked', $blockedVariation['status']);
        $this->assertContains('Missing SKU.', $blockedVariation['errors']);
        $this->assertContains('Missing both SKU and GTIN/EAN candidate.', $blockedVariation['errors']);
        $this->assertContains('Missing price candidate.', $blockedVariation['errors']);
    }

    public function test_woocommerce_product_discovery_limit_cannot_be_overridden_by_request_parameters(): void
    {
        config(['omnibridge.allow_connection_test_http' => true]);

        Http::fake([
            'https://woo.example.test/wp-json/wc/v3/products*' => Http::response([]),
        ]);

        [$user, $connection] = $this->connectionWithCredentials('woocommerce', [
            'consumer_key' => 'ck_secret',
            'consumer_secret' => 'cs_secret',
        ]);

        $this->actingAs($user)
            ->postJson("/connections/{$connection->id}/discover/products?limit=500&per_page=500")
            ->assertOk()
            ->assertJson(['status' => 'success']);

        Http::assertSent(fn ($request): bool => $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://woo.example.test/wp-json/wc/v3/products')
            && str_contains($request->url(), 'per_page=10'));
    }

    public function test_discovery_prunes_older_snapshots_to_latest_five_per_connection_and_type(): void
    {
        config(['omnibridge.allow_connection_test_http' => true]);

        Http::fake([
            'https://woo.example.test/wp-json/wc/v3/products*' => Http::response([]),
        ]);

        [$user, $connection] = $this->connectionWithCredentials('woocommerce', [
            'consumer_key' => 'ck_secret',
            'consumer_secret' => 'cs_secret',
        ]);

        for ($i = 0; $i < 5; $i++) {
            ConnectionDiscoverySnapshot::query()->create([
                'organization_id' => $connection->organization_id,
                'connection_id' => $connection->id,
                'source_system' => 'woocommerce',
                'discovery_type' => 'products',
                'status' => 'success',
                'summary_json' => ['sequence' => $i],
                'sample_json' => ['products' => []],
                'checked_at' => now()->subMinutes(10 - $i),
                'created_at' => now()->subMinutes(10 - $i),
                'updated_at' => now()->subMinutes(10 - $i),
            ]);
        }

        $this->actingAs($user)
            ->postJson("/connections/{$connection->id}/discover/products")
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $snapshots = ConnectionDiscoverySnapshot::query()
            ->where('connection_id', $connection->id)
            ->where('discovery_type', 'products')
            ->orderBy('created_at')
            ->get();

        $this->assertCount(5, $snapshots);
        $this->assertFalse($snapshots->pluck('summary_json')->contains(fn ($summary): bool => ($summary['sequence'] ?? null) === 0));
    }

    public function test_missing_woocommerce_settings_use_user_friendly_messages(): void
    {
        config(['omnibridge.allow_connection_test_http' => true]);
        Http::fake();

        [$user, $connection] = $this->connectionWithCredentials('woocommerce', [], '');

        $this->actingAs($user)
            ->postJson("/connections/{$connection->id}/discover/products")
            ->assertOk()
            ->assertJson([
                'status' => 'failed',
                'error_message' => 'Missing required settings: Missing WooCommerce site URL, Missing WooCommerce consumer key, Missing WooCommerce consumer secret',
            ]);

        Http::assertNothingSent();
    }

    public function test_missing_front_settings_use_user_friendly_messages(): void
    {
        config(['omnibridge.allow_connection_test_http' => true]);
        Http::fake();

        [$user, $connection] = $this->connectionWithCredentials('front_systems', [], '');

        $this->actingAs($user)
            ->postJson("/connections/{$connection->id}/discover/stores")
            ->assertOk()
            ->assertJson([
                'status' => 'failed',
                'error_message' => 'Missing required settings: Missing Front base URL, Missing Front API key',
            ]);

        Http::assertNothingSent();
    }

    public function test_lab_shows_discovery_buttons_and_status(): void
    {
        [$user, $connection] = $this->connectionWithCredentials('front_systems', [
            'api_key' => 'front-secret-key',
        ], 'https://front.example.test/restapi/V2');

        ConnectionDiscoverySnapshot::query()->create([
            'organization_id' => $connection->organization_id,
            'connection_id' => $connection->id,
            'source_system' => 'front_systems',
            'discovery_type' => 'products',
            'status' => 'success',
            'summary_json' => ['count' => 1],
            'sample_json' => ['products' => []],
            'checked_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/lab')
            ->assertOk()
            ->assertSee('Testing Lab')
            ->assertSee('Discover stores')
            ->assertSee('Discover products')
            ->assertSee('products');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Discover stores')
            ->assertDontSee('Discover products');
    }

    public function test_discovery_page_shows_mapping_preview_from_latest_samples(): void
    {
        [$user, $wooConnection] = $this->connectionWithCredentials('woocommerce', [
            'consumer_key' => 'ck_secret',
            'consumer_secret' => 'cs_secret',
        ]);

        $frontConnection = Connection::query()->create([
            'organization_id' => $wooConnection->organization_id,
            'type' => 'front_systems',
            'name' => 'Front staging',
            'base_url' => 'https://front.example.test/restapi/V2',
            'status' => 'pending',
        ]);

        ConnectionDiscoverySnapshot::query()->create([
            'organization_id' => $wooConnection->organization_id,
            'connection_id' => $wooConnection->id,
            'source_system' => 'woocommerce',
            'discovery_type' => 'products',
            'status' => 'success',
            'summary_json' => ['count' => 1],
            'sample_json' => [
                'products' => [
                    [
                        'id' => 123,
                        'name' => 'Woo Boot',
                        'sku' => 'BOOT-24',
                        'gtin_candidate' => ['value' => '7040000000012', 'key' => 'Zettle_barcode', 'confidence' => 'exact_known_field'],
                    ],
                ],
            ],
            'checked_at' => now(),
        ]);

        ConnectionDiscoverySnapshot::query()->create([
            'organization_id' => $wooConnection->organization_id,
            'connection_id' => $frontConnection->id,
            'source_system' => 'front_systems',
            'discovery_type' => 'products',
            'status' => 'success',
            'summary_json' => ['count' => 1],
            'sample_json' => [
                'products' => [
                    [
                        'productid' => 501,
                        'name' => 'Front Boot',
                        'brand' => 'Brand A',
                        'groupName' => 'Shoes',
                        'subgroupName' => 'Boots',
                        'productSizes' => [
                            ['gtin' => '7040000000012', 'identity' => 'IDENT-24', 'externalSKU' => 'BOOT-24', 'label' => '24'],
                        ],
                    ],
                ],
            ],
            'checked_at' => now(),
        ]);

        $this->actingAs($user)
            ->get("/connections/{$wooConnection->id}/discovery")
            ->assertOk()
            ->assertSee('Mapping Preview')
            ->assertSee('Woo Readiness Report')
            ->assertSee('Variation Sample')
            ->assertSee('Woo Boot')
            ->assertSee('Front Boot')
            ->assertSee('gtin')
            ->assertSee('high');

        $this->assertSame(0, DB::table('product_mappings')->count());
    }

    private function connectionWithCredentials(string $type, array $credentials, string $baseUrl = 'https://woo.example.test'): array
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

        $connection = Connection::query()->create([
            'organization_id' => $organization->id,
            'type' => $type,
            'name' => ucfirst($type) . ' staging',
            'base_url' => $baseUrl,
            'status' => 'pending',
        ]);

        $vault = app(CredentialVault::class);

        foreach ($credentials as $credentialType => $value) {
            $vault->store($connection, $credentialType, ['value' => $value]);
        }

        return [$user, $connection->fresh(['credentials'])];
    }
}
