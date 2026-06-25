<?php

namespace App\Services\Credentials;

use App\Models\Connection;
use App\Models\ConnectionCredential;
use Illuminate\Support\Arr;

class CredentialVault
{
    public function store(Connection $connection, string $credentialType, array $payload): ConnectionCredential
    {
        return $connection->credentials()->updateOrCreate(
            ['credential_type' => $credentialType],
            [
                'encrypted_payload' => $payload,
                'redacted_hint' => $this->redactedHint($payload),
                'rotated_at' => now(),
            ],
        );
    }

    private function redactedHint(array $payload): string
    {
        $candidate = Arr::first($payload, static fn ($value): bool => is_string($value) && $value !== '');

        if (! is_string($candidate)) {
            return '[configured]';
        }

        $suffix = substr($candidate, -4);

        return $suffix === '' ? '[configured]' : '...' . $suffix;
    }
}
