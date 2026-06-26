<?php

namespace App\Services\Security;

final class SecretRedactor
{
    private const SECRET_KEYS = [
        'api-key',
        'api_key',
        'apikey',
        'authorization',
        'bearer',
        'cookie',
        'consumer_key',
        'consumer_secret',
        'password',
        'secret',
        'signature',
        'set-cookie',
        'token',
        'webhook_secret',
        'x-api-key',
    ];

    public function redact(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if ($this->isSecretKey((string) $key)) {
                $payload[$key] = $this->redactedValue($value);
                continue;
            }

            if (is_array($value)) {
                $payload[$key] = $this->redact($value);
                continue;
            }
        }

        return $payload;
    }

    private function isSecretKey(string $key): bool
    {
        $normalized = strtolower($key);

        foreach (self::SECRET_KEYS as $secretKey) {
            if (str_contains($normalized, $secretKey)) {
                return true;
            }
        }

        return false;
    }

    private function redactedValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return '[redacted]';
        }

        return array_map(fn (): string => '[redacted]', $value);
    }
}
