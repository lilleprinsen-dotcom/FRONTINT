<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\Organization;
use App\Services\Security\SecretRedactor;
use App\Support\IdempotencyKey;

class EventRecorder
{
    public function __construct(
        private readonly SecretRedactor $redactor,
    ) {
    }

    public function record(
        Organization $organization,
        string $sourceSystem,
        string $eventType,
        ?string $sourceEventId,
        array $payload,
        array $metadata = [],
    ): Event {
        $sourceEventId ??= IdempotencyKey::payloadHash($payload);
        $idempotencyKey = IdempotencyKey::build($organization->slug, $sourceSystem, $eventType, $sourceEventId);

        return Event::firstOrCreate(
            [
                'organization_id' => $organization->id,
                'idempotency_key' => $idempotencyKey,
            ],
            [
                'source_system' => $sourceSystem,
                'event_type' => $eventType,
                'source_event_id' => $sourceEventId,
                'payload_json' => $this->redactor->redact($payload),
                'metadata_json' => $this->redactor->redact($metadata),
                'status' => 'received',
                'received_at' => now(),
            ],
        );
    }
}
