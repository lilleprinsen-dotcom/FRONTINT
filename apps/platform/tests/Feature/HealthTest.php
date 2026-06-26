<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'check' => 'ready',
                'database' => 'ok',
                'environment' => 'staging',
                'production_writes_enabled' => false,
            ]);
    }

    public function test_health_live_endpoint_returns_without_database_check(): void
    {
        $this->getJson('/health/live')
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'check' => 'live',
                'environment' => 'staging',
                'production_writes_enabled' => false,
            ])
            ->assertJsonMissing(['database' => 'ok']);
    }

    public function test_health_ready_endpoint_returns_database_readiness(): void
    {
        $this->getJson('/health/ready')
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'check' => 'ready',
                'database' => 'ok',
                'environment' => 'staging',
                'production_writes_enabled' => false,
            ]);
    }
}
