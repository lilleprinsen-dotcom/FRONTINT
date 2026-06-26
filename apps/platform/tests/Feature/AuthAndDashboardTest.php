<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_loads(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Log in');
    }

    public function test_login_works_with_existing_user(): void
    {
        User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'secret-password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticated();
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/dashboard')
            ->assertRedirect('/login');
    }

    public function test_authenticated_dashboard_loads(): void
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

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Production writes are disabled');
    }

    public function test_dashboard_warns_when_live_http_tests_are_enabled(): void
    {
        config(['omnibridge.allow_connection_test_http' => true]);

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

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Live read-only HTTP tests are enabled. No writes are allowed, but real external systems may be contacted.')
            ->assertSee('Safe Product Setup')
            ->assertSee('Products show Ready or Needs attention before any future sync.')
            ->assertSee('No sync is performed and no data is written.');
    }

    public function test_dashboard_shows_severe_warning_when_production_writes_are_enabled(): void
    {
        config(['omnibridge.allow_production_writes' => true]);

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

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Production writes are enabled. This should remain disabled until a production launch checklist has been completed.');
    }
}
