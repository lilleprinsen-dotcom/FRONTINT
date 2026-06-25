<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIntegrationEvent;
use App\Models\Organization;
use App\Services\Events\EventRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WooCommerceWebhookController extends Controller
{
    public function __construct(
        private readonly EventRecorder $events,
    ) {
    }

    public function __invoke(Request $request, string $tenant): JsonResponse
    {
        $organization = Organization::query()->where('slug', $tenant)->firstOrFail();

        // TODO: Verify X-WC-Webhook-Signature against configured tenant webhook secret.
        $event = $this->events->record(
            organization: $organization,
            sourceSystem: 'woocommerce',
            eventType: (string) $request->header('X-WC-Webhook-Topic', 'unknown'),
            sourceEventId: $request->header('X-WC-Webhook-ID'),
            payload: $request->all(),
        );

        ProcessIntegrationEvent::dispatch($event->id);

        return response()->json([
            'status' => 'accepted',
            'event_id' => $event->id,
        ], 202);
    }
}
