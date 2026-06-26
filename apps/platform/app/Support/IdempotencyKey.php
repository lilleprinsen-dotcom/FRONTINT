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
        $payload = self::sortRecursive($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private static function sortRecursive(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = self::sortRecursive($value);
            }
        }

        ksort($payload);

        return $payload;
    }
}
