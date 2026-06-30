<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\ConnectionDiscoverySnapshot;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WooReadinessDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_woo_readiness_requires_authentication(): void
    {
        $this->get('/woo-readiness')
            ->assertRedirect('/login');
    }

    public function test_woo_readiness_shows_empty_state_without_snapshot(): void
    {
        [$user] = $this->userWithOrganization();

        $this->actingAs($user)
            ->get('/woo-readiness')
            ->assertOk()
            ->assertSee('Woo Readiness')
            ->assertSee('No WooCommerce product discovery sample found yet')
            ->assertSee('Go to connections');
    }

    public function test_woo_readiness_summarizes_latest_woocommerce_snapshot_without_http_calls(): void
    {
        Http::fake();
        [$user, $organization] = $this->userWithOrganization();
        $connection = $this->connection($organization);

        $this->snapshot($organization, $connection, [
            $this->product(id: 100, sku: 'PARENT', gtin: null, type: 'variable', price: '499'),
            $this->product(id: 101, sku: 'SKU-ONLY', gtin: null, price: '199'),
            $this->product(id: 102, sku: '', gtin: null, price: '299'),
            $this->product(id: 103, sku: 'NO-PRICE', gtin: '7040000000103', price: ''),
        ], [
            $this->variation(id: 201, parentId: 100, sku: 'VAR-92', gtin: '7040000000201', price: '499'),
            $this->variation(id: 202, parentId: 100, sku: 'VAR-98', gtin: null, price: '499'),
            $this->variation(id: 203, parentId: 100, sku: 'DUP-SKU', gtin: '7040000000203', price: '499'),
            $this->variation(id: 204, parentId: 100, sku: 'DUP-SKU', gtin: '7040000000204', price: '499'),
            $this->variation(id: 205, parentId: 100, sku: 'VAR-DUP-GTIN-1', gtin: 'DUP-GTIN', price: '499'),
            $this->variation(id: 206, parentId: 100, sku: 'VAR-DUP-GTIN-2', gtin: 'DUP-GTIN', price: '499'),
        ]);

        $this->actingAs($user)
            ->get('/woo-readiness')
            ->assertOk()
            ->assertSee('Ready with SKU + EAN')
            ->assertSee('Ready with SKU only')
            ->assertSee('Sellable variations')
            ->assertSee('Duplicate SKUs')
            ->assertSee('DUP-SKU')
            ->assertSee('DUP-GTIN')
            ->assertSee('Missing both SKU and GTIN/EAN')
            ->assertSee('Missing price')
            ->assertSee('SKU fallback available')
            ->assertSee('variation:201')
            ->assertDontSee('Front product discovery');

        Http::assertNothingSent();
    }

    public function test_dashboard_links_to_woo_readiness(): void
    {
        [$user] = $this->userWithOrganization();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Woo Readiness')
            ->assertSee('Review Woo readiness');
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

    private function connection(Organization $organization): Connection
    {
        return Connection::query()->create([
            'organization_id' => $organization->id,
            'type' => 'woocommerce',
            'name' => 'WooCommerce staging',
            'base_url' => 'https://woo.example.test',
            'status' => 'success',
        ]);
    }

    private function snapshot(Organization $organization, Connection $connection, array $products, array $variations): ConnectionDiscoverySnapshot
    {
        return ConnectionDiscoverySnapshot::query()->create([
            'organization_id' => $organization->id,
            'connection_id' => $connection->id,
            'source_system' => 'woocommerce',
            'discovery_type' => 'products',
            'status' => 'success',
            'summary_json' => [
                'count' => count($products),
                'variation_count' => count($variations),
                'read_only' => true,
            ],
            'sample_json' => [
                'products' => $products,
                'variations' => $variations,
            ],
            'checked_at' => now(),
        ]);
    }

    private function product(
        int $id,
        ?string $sku,
        ?string $gtin,
        string $type = 'simple',
        ?string $price = '499',
    ): array {
        return [
            'id' => $id,
            'name' => "Product {$id}",
            'sku' => $sku,
            'type' => $type,
            'regular_price' => $price,
            'stock_status' => 'instock',
            'categories' => ['Category'],
            'gtin_candidate' => [
                'key' => $gtin ? '_izettle_barcode' : null,
                'value' => $gtin,
                'confidence' => $gtin ? 'exact_known_field' : 'none',
                'candidates' => [],
            ],
        ];
    }

    private function variation(int $id, int $parentId, ?string $sku, ?string $gtin, ?string $price): array
    {
        return [
            'id' => $id,
            'parent_id' => $parentId,
            'parent_name' => 'Parent product',
            'name' => "Variation {$id}",
            'sku' => $sku,
            'regular_price' => $price,
            'stock_status' => 'instock',
            'attributes' => ['92'],
            'gtin_candidate' => [
                'key' => $gtin ? '_izettle_barcode' : null,
                'value' => $gtin,
                'confidence' => $gtin ? 'exact_known_field' : 'none',
                'candidates' => [],
            ],
            'discovery_status' => 'success',
        ];
    }
}
