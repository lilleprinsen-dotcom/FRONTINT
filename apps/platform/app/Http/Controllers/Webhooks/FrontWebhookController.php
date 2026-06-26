<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIntegrationEvent;
use App\Models\WebhookEndpoint;
use App\Services\Events\EventRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FrontWebhookController extends Controller
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
            ->where('source_system', 'front')
            ->where('status', 'active')
            ->firstOrFail();

        // NEEDS_FRONT_CONFIRMATION: Replace with documented Front signature or token verification.
        $event = $this->events->record(
            organization: $webhookEndpoint->organization,
            sourceSystem: 'front',
            eventType: (string) $request->input('type', 'unknown'),
            sourceEventId: $request->input('id') ? (string) $request->input('id') : null,
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
