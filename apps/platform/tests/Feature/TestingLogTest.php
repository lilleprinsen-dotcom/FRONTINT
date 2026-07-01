<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Connection;
use App\Models\ConnectionDiscoverySnapshot;
use App\Models\Event;
use App\Models\Organization;
use App\Models\ProductSyncProfile;
use App\Models\ProductSyncRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TestingLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_testing_log_requires_authentication(): void
    {
        $this->get('/testing-log')
            ->assertRedirect('/login');
    }

    public function test_testing_log_shows_copyable_plain_language_results_without_secrets(): void
    {
        [$user, $organization] = $this->userWithOrganization();

        Connection::query()->create([
            'organization_id' => $organization->id,
            'type' => 'woocommerce',
            'name' => 'Woo staging',
            'base_url' => 'https://woo.example.test',
            'status' => 'success',
            'last_checked_at' => now(),
            'last_test_status' => 'success',
            'last_http_status' => 200,
            'last_response_time_ms' => 120,
            'last_error' => null,
            'last_test_metadata' => ['api_key' => 'should-not-show'],
        ]);

        $connection = Connection::query()->first();

        ConnectionDiscoverySnapshot::query()->create([
            'organization_id' => $organization->id,
            'connection_id' => $connection->id,
            'source_system' => 'woocommerce',
            'discovery_type' => 'products',
            'status' => 'success',
            'summary_json' => [
                'count' => 10,
                'variation_count' => 20,
                'endpoint' => 'GET /wp-json/wc/v3/products',
                'read_only' => true,
                'consumer_secret' => 'should-not-show',
            ],
            'sample_json' => ['secret' => 'should-not-show'],
            'checked_at' => now(),
        ]);

        $profile = ProductSyncProfile::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Default',
            'is_active' => true,
            'mode' => 'staging_batch',
            'sync_scope' => 'selected_only',
            'price_strategy' => 'regular_price_only',
            'stock_strategy' => 'do_not_sync_stock_yet',
            'product_identity_strategy' => 'woo_id_as_front_extid',
            'gtin_field_strategy' => 'auto_detect',
        ]);

        ProductSyncRun::query()->create([
            'organization_id' => $organization->id,
            'product_sync_profile_id' => $profile->id,
            'run_type' => 'staging_batch',
            'status' => 'completed_with_errors',
            'mode' => 'staging_batch',
            'scope' => 'selected_only',
            'total_candidates' => 3,
            'total_ready' => 2,
            'total_blocked' => 1,
            'total_synced' => 1,
            'total_failed' => 1,
            'total_skipped' => 0,
            'total_pending' => 0,
            'total_variations' => 2,
        ]);

        Event::query()->create([
            'organization_id' => $organization->id,
            'source_system' => 'woocommerce',
            'event_type' => 'product_updated',
            'source_event_id' => 'evt_1',
            'idempotency_key' => 'event-key',
            'payload_json' => ['authorization' => 'should-not-show'],
            'metadata_json' => ['x-api-key' => 'should-not-show'],
            'status' => 'received',
            'received_at' => now(),
        ]);

        AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'action' => 'front_product_write_test',
            'metadata_json' => [
                'status' => 'success',
                'selected_count' => 1,
                'token' => 'should-not-show',
            ],
        ]);

        $this->actingAs($user)
            ->get('/testing-log')
            ->assertOk()
            ->assertSee('Testing Log')
            ->assertSee('Copy this for Codex')
            ->assertSee('Connection test')
            ->assertSee('Discovery')
            ->assertSee('Product sync run')
            ->assertSee('Webhook event')
            ->assertSee('Worked')
            ->assertSee('Needs attention')
            ->assertSee('GET /wp-json/wc/v3/products')
            ->assertDontSee('should-not-show')
            ->assertDontSee('consumer_secret')
            ->assertDontSee('authorization')
            ->assertDontSee('x-api-key');
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
}
