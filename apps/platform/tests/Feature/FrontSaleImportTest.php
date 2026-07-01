<?php

namespace Tests\Feature;

use App\Jobs\ProcessIntegrationEvent;
use App\Jobs\RunFrontSaleStockAdjustment;
use App\Models\Connection;
use App\Models\Event;
use App\Models\FrontSaleImport;
use App\Models\Organization;
use App\Models\ProductMapping;
use App\Models\User;
use App\Services\Sales\FrontSaleImportRecorder;
use App\Services\Sales\FrontSaleImportRunner;
use App\Services\Sales\FrontSaleStockAdjustmentRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FrontSaleImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_front_sales_page_requires_authentication(): void
    {
        $this->get('/front-sales')
            ->assertRedirect('/login');
    }

    public function test_front_sale_event_creates_staged_import_with_matched_lines(): void
    {
        Queue::fake();
        [$user, $organization] = $this->userWithOrganization();
        $this->productMapping($organization);

        $event = $this->frontSaleEvent($organization);

        app(ProcessIntegrationEvent::class, ['eventId' => $event->id])->handle(app(FrontSaleImportRecorder::class));

        $import = FrontSaleImport::query()->firstOrFail();
        $this->assertSame('pending', $import->status);
        $this->assertSame('stock_only', $import->handling_mode);
        $this->assertSame('pending', $import->stock_status);
        $this->assertSame('not_imported', $import->order_import_status);
        $this->assertSame('SALE-1', $import->front_sale_id);
        $this->assertSame('RECEIPT-1', $import->front_receipt_id);
        $this->assertSame('matched', $import->line_items_json[0]['mapping_status']);
        $this->assertSame(123, $import->woo_order_payload_json['line_items'][0]['product_id']);
        Queue::assertPushed(RunFrontSaleStockAdjustment::class);

        $this->actingAs($user)
            ->get('/front-sales')
            ->assertOk()
            ->assertSee('Front Sales')
            ->assertSee('RECEIPT-1');
    }

    public function test_front_sale_import_blocks_when_line_has_no_product_mapping(): void
    {
        [, $organization] = $this->userWithOrganization();

        $import = app(FrontSaleImportRecorder::class)->record($organization, $this->salePayload());

        $this->assertSame('blocked', $import->status);
        $this->assertSame('blocked', $import->stock_status);
        $this->assertStringContainsString('could not be matched', $import->error_message);
        $this->assertSame('missing_product_mapping', $import->line_items_json[0]['mapping_status']);
    }

    public function test_front_sale_stock_adjustment_reduces_woo_stock_without_creating_order(): void
    {
        Http::fake([
            'woo.example.test/wp-json/wc/v3/products/123/variations/456' => Http::sequence()
                ->push(['id' => 456, 'stock_quantity' => 10], 200)
                ->push(['id' => 456, 'stock_quantity' => 8], 200),
            '*' => Http::response(['unexpected' => true], 500),
        ]);

        [$user, $organization] = $this->userWithOrganization();
        $this->wooConnection($organization);
        $this->productMapping($organization);
        $import = app(FrontSaleImportRecorder::class)->record($organization, $this->salePayload(quantity: 2, total: 998));

        $result = app(FrontSaleStockAdjustmentRunner::class)->run($import, $user);

        $this->assertSame('adjusted', $result['status']);
        $import->refresh();
        $this->assertSame('stock_adjusted', $import->status);
        $this->assertSame('adjusted', $import->stock_status);
        $this->assertSame('not_imported', $import->order_import_status);

        Http::assertSent(function ($request): bool {
            return $request->method() === 'GET'
                && (string) $request->url() === 'https://woo.example.test/wp-json/wc/v3/products/123/variations/456';
        });
        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->method() === 'PUT'
                && (string) $request->url() === 'https://woo.example.test/wp-json/wc/v3/products/123/variations/456'
                && ($payload['stock_quantity'] ?? null) === 8;
        });
        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/wp-json/wc/v3/orders'));
    }

    public function test_front_sale_stock_adjustment_is_not_processed_twice(): void
    {
        Http::fake([
            'woo.example.test/wp-json/wc/v3/products/123/variations/456' => Http::sequence()
                ->push(['id' => 456, 'stock_quantity' => 10], 200)
                ->push(['id' => 456, 'stock_quantity' => 9], 200),
        ]);

        [$user, $organization] = $this->userWithOrganization();
        $this->wooConnection($organization);
        $this->productMapping($organization);
        $import = app(FrontSaleImportRecorder::class)->record($organization, $this->salePayload());

        app(FrontSaleStockAdjustmentRunner::class)->run($import, $user);
        $result = app(FrontSaleStockAdjustmentRunner::class)->run($import->fresh(), $user);

        $this->assertSame('blocked', $result['status']);
        $this->assertStringContainsString('already been adjusted', implode(' ', $result['gate_errors']));
        Http::assertSentCount(2);
    }

    public function test_front_sale_import_posts_paid_woocommerce_order_and_creates_order_mapping(): void
    {
        Http::fake([
            'woo.example.test/wp-json/wc/v3/orders' => Http::response([
                'id' => 9001,
                'status' => 'completed',
                'number' => '9001',
            ], 201),
            '*' => Http::response(['unexpected' => true], 500),
        ]);

        [$user, $organization] = $this->userWithOrganization();
        $this->wooConnection($organization);
        $this->productMapping($organization);
        $import = app(FrontSaleImportRecorder::class)->record($organization, $this->salePayload());

        $result = app(FrontSaleImportRunner::class)->run($import, $user);

        $this->assertSame('imported', $result['status']);
        $import->refresh();
        $this->assertSame('imported', $import->order_import_status);
        $this->assertSame(9001, $import->orderMapping->woo_order_id);
        $this->assertSame('front_pos', $import->orderMapping->source);

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->method() === 'POST'
                && (string) $request->url() === 'https://woo.example.test/wp-json/wc/v3/orders'
                && ($payload['payment_method'] ?? null) === 'paid_in_front'
                && ($payload['set_paid'] ?? null) === true
                && ($payload['line_items'][0]['product_id'] ?? null) === 123
                && ($payload['line_items'][0]['variation_id'] ?? null) === 456
                && ($payload['billing']['email'] ?? null) === 'customer@example.test'
                && ($payload['billing']['phone'] ?? null) === '+4712345678'
                && collect($payload['meta_data'] ?? [])->contains(fn (array $meta): bool => ($meta['key'] ?? null) === '_omnibridge_front_stock_already_adjusted' && ($meta['value'] ?? null) === 'yes')
                && collect($payload['meta_data'] ?? [])->contains(fn (array $meta): bool => ($meta['key'] ?? null) === '_order_stock_reduced' && ($meta['value'] ?? null) === 'yes');
        });

        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), 'frontsystems'));
    }

    public function test_front_sale_import_does_not_write_when_gates_fail(): void
    {
        Http::fake();

        [$user, $organization] = $this->userWithOrganization();
        $this->productMapping($organization);
        $import = app(FrontSaleImportRecorder::class)->record($organization, $this->salePayload());

        $result = app(FrontSaleImportRunner::class)->run($import, $user);

        $this->assertSame('blocked', $result['status']);
        $this->assertStringContainsString('WooCommerce staging connection is required', implode(' ', $result['gate_errors']));
        Http::assertNothingSent();
    }

    public function test_front_sale_import_failure_records_safe_error_and_can_retry(): void
    {
        Http::fake([
            'woo.example.test/wp-json/wc/v3/orders' => Http::sequence()
                ->push(['message' => 'bad request', 'consumer_secret' => 'should-not-show'], 400)
                ->push(['id' => 9002, 'status' => 'completed', 'number' => '9002'], 201),
        ]);

        [$user, $organization] = $this->userWithOrganization();
        $this->wooConnection($organization);
        $this->productMapping($organization);
        $import = app(FrontSaleImportRecorder::class)->record($organization, $this->salePayload());

        app(FrontSaleImportRunner::class)->run($import, $user);

        $import->refresh();
        $this->assertSame('failed', $import->order_import_status);
        $this->assertSame('HTTP 400', $import->error_message);
        $this->assertStringNotContainsString('should-not-show', json_encode($import->last_response_summary_json));

        app(FrontSaleImportRunner::class)->run($import, $user);

        $import->refresh();
        $this->assertSame('imported', $import->order_import_status);
        $this->assertSame(9002, $import->orderMapping->woo_order_id);
    }

    public function test_front_sale_import_recorder_is_idempotent(): void
    {
        [, $organization] = $this->userWithOrganization();
        $this->productMapping($organization);

        $first = app(FrontSaleImportRecorder::class)->record($organization, $this->salePayload());
        $second = app(FrontSaleImportRecorder::class)->record($organization, $this->salePayload());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, FrontSaleImport::query()->count());
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

    private function wooConnection(Organization $organization): Connection
    {
        $connection = Connection::query()->create([
            'organization_id' => $organization->id,
            'type' => 'woocommerce',
            'name' => 'Woo staging',
            'base_url' => 'https://woo.example.test',
            'status' => 'success',
        ]);

        $connection->credentials()->create([
            'credential_type' => 'consumer_key',
            'encrypted_payload' => ['value' => 'ck_test'],
            'redacted_hint' => '...test',
            'rotated_at' => now(),
        ]);
        $connection->credentials()->create([
            'credential_type' => 'consumer_secret',
            'encrypted_payload' => ['value' => 'cs_test'],
            'redacted_hint' => '...test',
            'rotated_at' => now(),
        ]);

        return $connection->load('credentials');
    }

    private function productMapping(Organization $organization): ProductMapping
    {
        return ProductMapping::query()->create([
            'organization_id' => $organization->id,
            'woo_item_key' => 'variation:456',
            'woo_product_id' => 123,
            'woo_variation_id' => 456,
            'front_product_id' => '321',
            'front_product_ext_id' => 'woo-variation-456',
            'front_identity' => 'IDENTITY-456',
            'sku' => 'SKU-456',
            'gtin' => '7040000000456',
            'external_sku' => 'SKU-456',
            'sync_status' => 'synced',
            'last_synced_at' => now(),
        ]);
    }

    private function frontSaleEvent(Organization $organization): Event
    {
        return Event::query()->create([
            'organization_id' => $organization->id,
            'source_system' => 'front',
            'event_type' => 'sale_created',
            'source_event_id' => 'SALE-1',
            'idempotency_key' => 'front-sale-created-sale-1',
            'payload_json' => $this->salePayload(),
            'metadata_json' => ['source' => 'test'],
            'status' => 'received',
            'received_at' => now(),
        ]);
    }

    private function salePayload(int $quantity = 1, int $total = 499): array
    {
        return [
            'saleId' => 'SALE-1',
            'receiptId' => 'RECEIPT-1',
            'currency' => 'NOK',
            'totalAmount' => $total,
            'customer' => [
                'firstName' => 'Test',
                'lastName' => 'Customer',
                'email' => 'customer@example.test',
                'phone' => '+4712345678',
            ],
            'lines' => [
                [
                    'name' => 'Test product',
                    'quantity' => $quantity,
                    'unitPrice' => 499,
                    'total' => $total,
                    'gtin' => '7040000000456',
                    'externalSKU' => 'SKU-456',
                    'identity' => 'IDENTITY-456',
                ],
            ],
        ];
    }
}
