<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIntegrationEvent;
use App\Models\Organization;
use App\Services\Events\EventRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FrontWebhookController extends Controller
{
    public function __construct(
        private readonly EventRecorder $events,
    ) {
    }

    public function __invoke(Request $request, string $tenant): JsonResponse
    {
        $organization = Organization::query()->where('slug', $tenant)->firstOrFail();

        // NEEDS_FRONT_CONFIRMATION: Replace with documented Front signature or token verification.
        $event = $this->events->record(
            organization: $organization,
            sourceSystem: 'front',
            eventType: (string) $request->input('type', 'unknown'),
            sourceEventId: $request->input('id') ? (string) $request->input('id') : null,
            payload: $request->all(),
        );

        ProcessIntegrationEvent::dispatch($event->id);

        return response()->json([
            'status' => 'accepted',
            'event_id' => $event->id,
        ], 202);
    }
}
