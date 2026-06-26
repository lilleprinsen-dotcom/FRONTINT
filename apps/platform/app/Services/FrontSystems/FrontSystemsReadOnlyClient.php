<?php

namespace App\Services\FrontSystems;

use App\Models\Connection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class FrontSystemsReadOnlyClient
{
    public function test(Connection $connection): array
    {
        try {
            $response = $this->environment($connection);

            return [
                'status' => $response->successful() ? 'connected' : 'http_error',
                'message' => $response->successful()
                    ? 'Front Systems REST API responded to a read-only environment check.'
                    : 'Front Systems REST API responded with a non-success status.',
                'service' => 'front',
                'operation' => 'GET /api/Environment',
                'http_status' => $response->status(),
                'http_checked' => true,
                'read_only' => true,
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'unreachable',
                'message' => 'Front Systems REST API could not be reached by the read-only check.',
                'service' => 'front',
                'operation' => 'GET /api/Environment',
                'error_class' => $exception::class,
                'http_checked' => true,
                'read_only' => true,
            ];
        }
    }

    public function environment(Connection $connection): Response
    {
        return $this->request($connection)
            ->get($this->url($connection, '/api/Environment'));
    }

    public function stores(Connection $connection): Response
    {
        return $this->request($connection)
            ->get($this->url($connection, '/api/Stores'));
    }

    private function request(Connection $connection): PendingRequest
    {
        return Http::timeout(10)
            ->acceptJson()
            ->withHeaders([
                'x-api-key' => $this->credentialValue($connection, 'api_key') ?? '',
            ]);
    }

    private function url(Connection $connection, string $path): string
    {
        return rtrim((string) $connection->base_url, '/') . $path;
    }

    private function credentialValue(Connection $connection, string $type): ?string
    {
        $payload = $connection->credential($type)?->encrypted_payload;
        $value = is_array($payload) ? ($payload['value'] ?? null) : null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
