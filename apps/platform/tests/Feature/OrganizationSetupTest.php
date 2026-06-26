<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrganizationSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_can_be_created(): void
    {
        $user = $this->adminUser();

        $this->actingAs($user)
            ->post('/organizations', [
                'name' => 'Lilleprinsen',
                'slug' => 'lilleprinsen',
                'environment' => 'staging',
                'status' => 'active',
            ])
            ->assertRedirect('/dashboard');

        $this->assertDatabaseHas('organizations', [
            'slug' => 'lilleprinsen',
            'environment' => 'staging',
        ]);
    }

    public function test_default_webhook_endpoints_are_provisioned_for_an_organization(): void
    {
        $user = $this->adminUser();

        $this->actingAs($user)
            ->post('/organizations', [
                'name' => 'Lilleprinsen',
                'slug' => 'lilleprinsen',
                'environment' => 'staging',
                'status' => 'active',
            ]);

        $organization = Organization::query()->where('slug', 'lilleprinsen')->firstOrFail();

        $this->assertDatabaseHas('webhook_endpoints', [
            'organization_id' => $organization->id,
            'source_system' => 'woocommerce',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('webhook_endpoints', [
            'organization_id' => $organization->id,
            'source_system' => 'front',
            'status' => 'active',
        ]);
    }

    private function adminUser(): User
    {
        return User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret-password'),
        ]);
    }
}
