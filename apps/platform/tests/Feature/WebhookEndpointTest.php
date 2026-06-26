<?php

namespace Tests\Feature;

use App\Jobs\ProcessIntegrationEvent;
use App\Models\Event;
use App\Models\Organization;
use App\Models\WebhookEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_woocommerce_webhook_accepts_dummy_payload_via_path_token(): void
    {
        Queue::fake();
        $endpoint = $this->endpoint('woocommerce');

        $this->postJson("/webhooks/woocommerce/{$endpoint->path_token}", ['order_id' => 123], [
            'X-WC-Webhook-ID' => 'woo-event-1',
            'X-WC-Webhook-Topic' => 'order.created',
            'Authorization' => 'Bearer should-redact',
            'X-API-Key' => 'should-redact',
        ])
            ->assertAccepted()
            ->assertJson(['status' => 'accepted']);

        $event = Event::query()->firstOrFail();

        $this->assertSame('woocommerce', $event->source_system);
        $this->assertSame('order.created', $event->event_type);
        $this->assertSame('[redacted]', $event->metadata_json['headers']['authorization'][0]);
        $this->assertSame('[redacted]', $event->metadata_json['headers']['x-api-key'][0]);
        Queue::assertPushed(ProcessIntegrationEvent::class, 1);
    }

    public function test_front_webhook_accepts_dummy_payload_via_path_token(): void
    {
        Queue::fake();
        $endpoint = $this->endpoint('front');

        $this->postJson("/webhooks/front/{$endpoint->path_token}", [
            'id' => 'front-event-1',
            'type' => 'sale.created',
            'token' => 'should-redact',
        ])
            ->assertAccepted()
            ->assertJson(['status' => 'accepted']);

        $event = Event::query()->firstOrFail();

        $this->assertSame('front', $event->source_system);
        $this->assertSame('sale.created', $event->event_type);
        $this->assertSame('[redacted]', $event->payload_json['token']);
        Queue::assertPushed(ProcessIntegrationEvent::class, 1);
    }

    public function test_duplicate_webhook_payload_does_not_dispatch_duplicate_processing(): void
    {
        Queue::fake();
        $endpoint = $this->endpoint('front');
        $payload = ['id' => 'front-event-duplicate', 'type' => 'sale.created'];

        $this->postJson("/webhooks/front/{$endpoint->path_token}", $payload)
            ->assertAccepted()
            ->assertJson(['status' => 'accepted']);

        $this->postJson("/webhooks/front/{$endpoint->path_token}", $payload)
            ->assertAccepted()
            ->assertJson(['status' => 'duplicate_accepted']);

        $this->assertSame(1, Event::query()->count());
        Queue::assertPushed(ProcessIntegrationEvent::class, 1);
    }

    public function test_invalid_webhook_path_token_returns_404(): void
    {
        $this->postJson('/webhooks/front/not-a-real-token', ['id' => 'event-1'])
            ->assertNotFound();
    }

    private function endpoint(string $sourceSystem): WebhookEndpoint
    {
        $organization = Organization::query()->create([
            'name' => 'Lilleprinsen',
            'slug' => 'lilleprinsen',
            'environment' => 'staging',
            'status' => 'active',
        ]);

        return WebhookEndpoint::query()->create([
            'organization_id' => $organization->id,
            'source_system' => $sourceSystem,
            'path_token' => "{$sourceSystem}-token",
            'status' => 'active',
        ]);
    }
}
