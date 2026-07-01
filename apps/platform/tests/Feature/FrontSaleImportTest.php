<?php

namespace Tests\Feature;

use App\Jobs\ProcessIntegrationEvent;
use App\Models\Connection;
use App\Models\Event;
use App\Models\FrontSaleImport;
use App\Models\Organization;
use App\Models\ProductMapping;
use App\Models\User;
use App\Services\Sales\FrontSaleImportRecorder;
use App\Services\Sales\FrontSaleImportRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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
        [$user, $organization] = $this->userWithOrganization();
        $this->productMapping($organization);

        $event = $this->frontSaleEvent($organization);

        app(ProcessIntegrationEvent::class, ['eventId' => $event->id])->handle(app(FrontSaleImportRecorder::class));

        $import = FrontSaleImport::query()->firstOrFail();
        $this->assertSame('pending', $import->status);
        $this->assertSame('SALE-1', $import->front_sale_id);
        $this->assertSame('RECEIPT-1', $import->front_receipt_id);
        $this->assertSame('matched', $import->line_items_json[0]['mapping_status']);
        $this->assertSame(123, $import->woo_order_payload_json['line_items'][0]['product_id']);

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
        $this->assertStringContainsString('could not be matched', $import->error_message);
        $this->assertSame('missing_product_mapping', $import->line_items_json[0]['mapping_status']);
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
        $this->assertSame('imported', $import->status);
        $this->assertSame(9001, $import->orderMapping->woo_order_id);
        $this->assertSame('front_pos', $import->orderMapping->source);

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->method() === 'POST'
                && (string) $request->url() === 'https://woo.example.test/wp-json/wc/v3/orders'
                && ($payload['payment_method'] ?? null) === 'paid_in_front'
                && ($payload['set_paid'] ?? null) === true
                && ($payload['line_items'][0]['product_id'] ?? null) === 123
                && ($payload['line_items'][0]['variation_id'] ?? null) === 456;
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
        $this->assertSame('failed', $import->status);
        $this->assertSame('HTTP 400', $import->error_message);
        $this->assertStringNotContainsString('should-not-show', json_encode($import->last_response_summary_json));

        app(FrontSaleImportRunner::class)->run($import, $user);

        $import->refresh();
        $this->assertSame('imported', $import->status);
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

    private function salePayload(): array
    {
        return [
            'saleId' => 'SALE-1',
            'receiptId' => 'RECEIPT-1',
            'currency' => 'NOK',
            'totalAmount' => 499,
            'lines' => [
                [
                    'name' => 'Test product',
                    'quantity' => 1,
                    'unitPrice' => 499,
                    'total' => 499,
                    'gtin' => '7040000000456',
                    'externalSKU' => 'SKU-456',
                    'identity' => 'IDENTITY-456',
                ],
            ],
        ];
    }
}
