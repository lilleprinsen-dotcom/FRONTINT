<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIntegrationEvent;
use App\Models\WebhookEndpoint;
use App\Services\Events\EventRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WooCommerceWebhookController extends Controller
{
    public function __construct(
        private readonly EventRecorder $events,
    ) {
    }

    public function __invoke(Request $request, string $pathToken): JsonResponse
    {
        $webhookEndpoint = WebhookEndpoint::query()
            ->with('organization')
            ->where('path_token', $pathToken)
            ->where('source_system', 'woocommerce')
            ->where('status', 'active')
            ->firstOrFail();

        // TODO: Verify X-WC-Webhook-Signature against configured tenant webhook secret.
        $event = $this->events->record(
            organization: $webhookEndpoint->organization,
            sourceSystem: 'woocommerce',
            eventType: (string) $request->header('X-WC-Webhook-Topic', 'unknown'),
            sourceEventId: $request->header('X-WC-Webhook-ID'),
            payload: $request->all(),
            metadata: [
                'webhook_endpoint_id' => $webhookEndpoint->id,
                'source_ip' => $request->ip(),
                'headers' => $request->headers->all(),
                'path' => $request->path(),
                'method' => $request->method(),
            ],
        );

        if (! $event->wasRecentlyCreated) {
            return response()->json([
                'status' => 'duplicate_accepted',
                'event_id' => $event->id,
            ], 202);
        }

        ProcessIntegrationEvent::dispatch($event->id);

        return response()->json([
            'status' => 'accepted',
            'event_id' => $event->id,
        ], 202);
    }
}
