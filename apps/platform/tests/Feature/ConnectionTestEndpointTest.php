<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Services\Credentials\CredentialVault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConnectionTestEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_connection_test_does_not_call_http_when_live_checks_are_disabled(): void
    {
        config(['omnibridge.allow_connection_test_http' => false]);
        Http::fake();

        [$user, $connection] = $this->connectionWithCredentials('woocommerce', [
            'consumer_key' => 'ck_test',
            'consumer_secret' => 'cs_test',
        ]);

        $this->actingAs($user)
            ->postJson("/connections/{$connection->id}/test")
            ->assertOk()
            ->assertJson([
                'status' => 'skipped',
                'http_checked' => false,
            ]);

        Http::assertNothingSent();
        $connection->refresh();
        $this->assertSame('skipped', $connection->status);
        $this->assertSame('skipped', $connection->last_test_status);
        $this->assertNotNull($connection->last_checked_at);
    }

    public function test_front_connection_test_does_not_call_http_when_live_checks_are_disabled(): void
    {
        config(['omnibridge.allow_connection_test_http' => false]);
        Http::fake();

        [$user, $connection] = $this->connectionWithCredentials('front_systems', [
            'api_key' => 'front-key-test',
        ], 'https://front.example.test/restapi/V2');

        $this->actingAs($user)
            ->postJson("/connections/{$connection->id}/test")
            ->assertOk()
            ->assertJson([
                'status' => 'skipped',
                'http_checked' => false,
            ]);

        Http::assertNothingSent();
        $this->assertSame('skipped', $connection->fresh()->status);
    }

    public function test_woocommerce_connection_test_uses_read_only_system_status_endpoint(): void
    {
        config(['omnibridge.allow_connection_test_http' => true]);

        Http::fake([
            'https://woo.example.test/wp-json/wc/v3/system_status' => Http::response([
                'environment' => ['version' => 'test'],
            ]),
        ]);

        [$user, $connection] = $this->connectionWithCredentials('woocommerce', [
            'consumer_key' => 'ck_test',
            'consumer_secret' => 'cs_test',
        ]);

        $this->actingAs($user)
            ->postJson("/connections/{$connection->id}/test")
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'service' => 'woocommerce',
                'operation' => 'GET /wp-json/wc/v3/system_status',
                'http_checked' => true,
                'read_only' => true,
            ]);

        Http::assertSent(fn ($request): bool => $request->method() === 'GET'
            && $request->url() === 'https://woo.example.test/wp-json/wc/v3/system_status'
            && $request->hasHeader('Authorization'));

        Http::assertNotSent(fn ($request): bool => in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true));

        $connection->refresh();
        $this->assertSame('success', $connection->status);
        $this->assertSame('success', $connection->last_test_status);
        $this->assertSame(200, $connection->last_http_status);
        $this->assertIsInt($connection->last_response_time_ms);
        $this->assertNull($connection->last_error);
        $this->assertNotNull($connection->last_checked_at);

        $auditLog = AuditLog::query()->where('action', 'live_readonly_connection_test')->firstOrFail();
        $this->assertSame($user->id, $auditLog->user_id);
        $this->assertSame($connection->organization_id, $auditLog->organization_id);
        $this->assertSame($connection->id, $auditLog->metadata_json['connection_id']);
        $this->assertSame('GET /wp-json/wc/v3/system_status', $auditLog->metadata_json['endpoint_group']);
        $this->assertSame('success', $auditLog->metadata_json['status']);
        $this->assertTrue($auditLog->metadata_json['live_http_enabled']);
        $this->assertTrue($auditLog->metadata_json['production_writes_disabled']);
        $this->assertStringNotContainsString('ck_test', json_encode($auditLog->metadata_json));
    }

    public function test_missing_connection_credentials_prevent_http_calls(): void
    {
        config(['omnibridge.allow_connection_test_http' => true]);
        Http::fake();

        [$user, $connection] = $this->connectionWithCredentials('woocommerce', [], '');

        $this->actingAs($user)
            ->postJson("/connections/{$connection->id}/test")
            ->assertOk()
            ->assertJson([
                'status' => 'failed',
                'last_error' => 'Missing required settings: Missing WooCommerce site URL, Missing WooCommerce consumer key, Missing WooCommerce consumer secret',
            ]);

        Http::assertNothingSent();
    }

    public function test_front_connection_test_uses_read_only_environment_endpoint(): void
    {
        config(['omnibridge.allow_connection_test_http' => true]);

        Http::fake([
            'https://front.example.test/restapi/V2/api/Environment' => Http::response([
                'environment' => 'test',
            ]),
            'https://front.example.test/restapi/V2/api/Stores' => Http::response([
                [
                    'StoreId' => 1001,
                    'StoreName' => 'Lilleprinsen Test Store',
                    'StockId' => 2001,
                    'Currency' => 'NOK',
                    'TimeZoneInfo' => 'Europe/Oslo',
                    'Email' => 'do-not-store@example.test',
                ],
            ]),
        ]);

        [$user, $connection] = $this->connectionWithCredentials('front_systems', [
            'api_key' => 'front-key-test',
        ], 'https://front.example.test/restapi/V2');

        $this->actingAs($user)
            ->postJson("/connections/{$connection->id}/test")
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'service' => 'front_systems',
                'operation' => 'GET /api/Environment',
                'http_checked' => true,
                'read_only' => true,
            ]);

        Http::assertSent(fn ($request): bool => $request->method() === 'GET'
            && $request->url() === 'https://front.example.test/restapi/V2/api/Environment'
            && $request->hasHeader('x-api-key', 'front-key-test'));

        Http::assertSent(fn ($request): bool => $request->method() === 'GET'
            && $request->url() === 'https://front.example.test/restapi/V2/api/Stores');

        Http::assertNotSent(fn ($request): bool => in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true));

        $connection->refresh();
        $this->assertSame('success', $connection->status);
        $this->assertSame(200, $connection->last_http_status);
        $this->assertSame('Lilleprinsen Test Store', $connection->last_test_metadata['front_stores'][0]['store_name']);
        $this->assertSame(2001, $connection->last_test_metadata['front_stores'][0]['stock_id']);
        $this->assertArrayNotHasKey('Email', $connection->last_test_metadata['front_stores'][0]);
    }

    public function test_failed_http_connection_records_safe_error(): void
    {
        config(['omnibridge.allow_connection_test_http' => true]);

        Http::fake([
            'https://woo.example.test/wp-json/wc/v3/system_status' => Http::response(['message' => 'sensitive body'], 401),
        ]);

        [$user, $connection] = $this->connectionWithCredentials('woocommerce', [
            'consumer_key' => 'ck_test',
            'consumer_secret' => 'cs_test',
        ]);

        $this->actingAs($user)
            ->postJson("/connections/{$connection->id}/test")
            ->assertOk()
            ->assertJson([
                'status' => 'failed',
                'http_status' => 401,
                'last_error' => 'HTTP 401',
            ]);

        $connection->refresh();
        $this->assertSame('failed', $connection->last_test_status);
        $this->assertSame(401, $connection->last_http_status);
        $this->assertSame('HTTP 401', $connection->last_error);
        $this->assertNull($connection->last_test_metadata);
    }

    public function test_connections_page_shows_safe_connection_status_context(): void
    {
        [$user, $connection] = $this->connectionWithCredentials('front_systems', [
            'api_key' => 'front-key-test',
        ], 'https://front.example.test/restapi/V2');

        $connection->update([
            'status' => 'success',
            'last_test_status' => 'success',
            'last_checked_at' => now(),
            'last_http_status' => 200,
            'last_response_time_ms' => 12,
            'last_test_metadata' => [
                'front_stores' => [
                    [
                        'store_id' => 1001,
                        'store_name' => 'Lilleprinsen Test Store',
                        'stock_id' => 2001,
                        'currency' => 'NOK',
                        'time_zone' => 'Europe/Oslo',
                    ],
                ],
            ],
        ]);

        $this->actingAs($user)
            ->get('/connections')
            ->assertOk()
            ->assertSee('Safe mode is on. Test buttons update status without contacting external systems.')
            ->assertSee('Last test: success')
            ->assertSee('No error saved')
            ->assertDontSee('Lilleprinsen Test Store')
            ->assertDontSee('Stock ID: 2001');
    }

    public function test_connection_credentials_are_not_displayed(): void
    {
        [$user] = $this->connectionWithCredentials('woocommerce', [
            'consumer_key' => 'ck_secret_value',
            'consumer_secret' => 'cs_secret_value',
        ]);

        $this->actingAs($user)
            ->get('/connections')
            ->assertOk()
            ->assertSee('2 credential field(s) configured')
            ->assertDontSee('ck_secret_value')
            ->assertDontSee('cs_secret_value')
            ->assertDontSee('...alue');

        $this->actingAs($user)
            ->get('/connections/create?type=woocommerce')
            ->assertOk()
            ->assertDontSee('ck_secret_value')
            ->assertDontSee('cs_secret_value');
    }

    public function test_dashboard_does_not_show_front_store_metadata(): void
    {
        [$user] = $this->connectionWithCredentials('front_systems', [
            'api_key' => 'front-key-test',
        ], 'https://front.example.test/restapi/V2');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertDontSee('Store ID:');
    }

    public function test_connection_form_marks_credential_panels_by_connection_type(): void
    {
        [$user] = $this->connectionWithCredentials('front_systems', [
            'api_key' => 'front-key-test',
        ], 'https://front.example.test/restapi/V2');

        $this->actingAs($user)
            ->get('/connections/create?type=front_systems')
            ->assertOk()
            ->assertSee('data-credential-panel="front_systems"', false)
            ->assertSee('data-credential-panel="woocommerce"', false)
            ->assertSee('data-credential-panel="webtoffee_adapter"', false)
            ->assertSee('WooCommerce site URL')
            ->assertSee('hidden', false)
            ->assertSee('data-connection-type-select', false);
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
