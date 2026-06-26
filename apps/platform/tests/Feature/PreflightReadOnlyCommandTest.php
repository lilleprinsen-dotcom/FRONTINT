<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\Organization;
use App\Models\User;
use App\Services\Credentials\CredentialVault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PreflightReadOnlyCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_preflight_command_reports_readiness_without_exposing_secrets(): void
    {
        $connection = $this->connectionWithCredentials('front_systems', [
            'api_key' => 'front-super-secret-key',
        ], 'https://front.example.test/restapi/V2');

        $exitCode = Artisan::call('omnibridge:preflight-readonly');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('APP_ENV: testing', $output);
        $this->assertStringContainsString('OMNIBRIDGE_ALLOW_PRODUCTION_WRITES: false', $output);
        $this->assertStringContainsString('OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP: false', $output);
        $this->assertStringContainsString('Database connection: sqlite', $output);
        $this->assertStringContainsString('Organizations: 1', $output);
        $this->assertStringContainsString('Connections: 1', $output);
        $this->assertStringContainsString('Front OpenAPI file: present', $output);
        $this->assertStringContainsString('Connections with credentials configured: 1', $output);
        $this->assertStringContainsString('Safe for read-only tests', $output);
        $this->assertStringNotContainsString('front-super-secret-key', $output);
        $this->assertSame('front_systems', $connection->type);
    }

    public function test_preflight_command_warns_if_production_writes_are_enabled(): void
    {
        config(['omnibridge.allow_production_writes' => true]);

        $exitCode = Artisan::call('omnibridge:preflight-readonly');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('WARNING: Production writes are enabled.', $output);
        $this->assertStringContainsString('Not safe for read-only tests', $output);
    }

    private function connectionWithCredentials(string $type, array $credentials, string $baseUrl): Connection
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

        return $connection->fresh(['credentials']);
    }
}
