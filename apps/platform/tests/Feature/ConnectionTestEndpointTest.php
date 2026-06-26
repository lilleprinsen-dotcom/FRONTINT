<?php

namespace Tests\Feature;

use App\Models\Connection;
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
                'status' => 'configured',
                'http_checked' => false,
            ]);

        Http::assertNothingSent();
        $this->assertSame('configured', $connection->fresh()->status);
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
                'status' => 'connected',
                'service' => 'woocommerce',
                'operation' => 'GET /wp-json/wc/v3/system_status',
                'http_checked' => true,
                'read_only' => true,
            ]);

        Http::assertSent(fn ($request): bool => $request->method() === 'GET'
            && $request->url() === 'https://woo.example.test/wp-json/wc/v3/system_status'
            && $request->hasHeader('Authorization'));

        $this->assertSame('connected', $connection->fresh()->status);
    }

    public function test_front_connection_test_uses_read_only_environment_endpoint(): void
    {
        config(['omnibridge.allow_connection_test_http' => true]);

        Http::fake([
            'https://front.example.test/restapi/V2/api/Environment' => Http::response([
                'environment' => 'test',
            ]),
        ]);

        [$user, $connection] = $this->connectionWithCredentials('front', [
            'api_key' => 'front-key-test',
        ], 'https://front.example.test/restapi/V2');

        $this->actingAs($user)
            ->postJson("/connections/{$connection->id}/test")
            ->assertOk()
            ->assertJson([
                'status' => 'connected',
                'service' => 'front',
                'operation' => 'GET /api/Environment',
                'http_checked' => true,
                'read_only' => true,
            ]);

        Http::assertSent(fn ($request): bool => $request->method() === 'GET'
            && $request->url() === 'https://front.example.test/restapi/V2/api/Environment'
            && $request->hasHeader('x-api-key', 'front-key-test'));

        $this->assertSame('connected', $connection->fresh()->status);
    }

    public function test_dashboard_shows_safe_connection_status_context(): void
    {
        [$user] = $this->connectionWithCredentials('front', [
            'api_key' => 'front-key-test',
        ], 'https://front.example.test/restapi/V2');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Live HTTP connection checks are disabled')
            ->assertSee('Read-only API test')
            ->assertSee('Not checked yet');
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
