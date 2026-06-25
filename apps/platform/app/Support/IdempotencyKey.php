<?php

namespace App\Support;

final class IdempotencyKey
{
    public static function build(string $tenant, string $sourceSystem, string $eventType, string $sourceEventId): string
    {
        $parts = array_map(
            static fn (string $part): string => str_replace(':', '-', strtolower(trim($part))),
            [$tenant, $sourceSystem, $eventType, $sourceEventId],
        );

        return implode(':', $parts);
    }

    public static function fromPayload(string $tenant, string $sourceSystem, string $eventType, array $payload): string
    {
        return self::build($tenant, $sourceSystem, $eventType, self::payloadHash($payload));
    }

    public static function payloadHash(array $payload): string
    {
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
