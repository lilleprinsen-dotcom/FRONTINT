<?php

namespace App\Services\FrontSystems;

use App\Models\Connection;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class FrontSystemsWebhookClient
{
    public function webhooks(Connection $connection): Response
    {
        return Http::timeout(10)
            ->acceptJson()
            ->withHeaders([
                'x-api-key' => $this->apiKey($connection),
            ])
            ->get($this->url($connection, '/api/Webhooks'));
    }

    public function webhookTypeSchema(Connection $connection, string $webhookType): Response
    {
        return Http::timeout(10)
            ->acceptJson()
            ->withHeaders([
                'x-api-key' => $this->apiKey($connection),
            ])
            ->get($this->url($connection, '/api/WebhooksTypes/' . rawurlencode($webhookType) . '/schema'));
    }

    public function createWebhook(Connection $connection, array $payload): Response
    {
        return Http::timeout(15)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'x-api-key' => $this->apiKey($connection),
            ])
            ->post($this->url($connection, '/api/Webhooks'), $payload);
    }

    public function updateWebhook(Connection $connection, string $frontWebhookId, array $payload): Response
    {
        return Http::timeout(15)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'x-api-key' => $this->apiKey($connection),
            ])
            ->put($this->url($connection, '/api/Webhooks/' . rawurlencode($frontWebhookId)), $payload);
    }

    public function hasApiKey(Connection $connection): bool
    {
        return $this->apiKey($connection) !== '';
    }

    public function safeWebhooks(mixed $payload): array
    {
        return collect($this->listPayload($payload, ['webhooks', 'Webhooks', 'items', 'Items']))
            ->filter(fn ($webhook): bool => is_array($webhook))
            ->take(100)
            ->map(fn (array $webhook): array => $this->safeWebhook($webhook))
            ->filter(fn (array $webhook): bool => array_filter($webhook) !== [])
            ->values()
            ->all();
    }

    public function safeWebhook(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        return [
            'id' => $payload['id'] ?? $payload['Id'] ?? $payload['webhookId'] ?? $payload['WebhookId'] ?? null,
            'store_id' => $payload['storeId'] ?? $payload['StoreId'] ?? null,
            'event' => $payload['event'] ?? $payload['Event'] ?? $payload['webhookType'] ?? $payload['WebhookType'] ?? null,
            'url' => $payload['url'] ?? $payload['Url'] ?? $payload['callbackUrl'] ?? $payload['CallbackUrl'] ?? null,
        ];
    }

    private function apiKey(Connection $connection): string
    {
        $payload = $connection->credential('api_key')?->encrypted_payload;
        $value = is_array($payload) ? ($payload['value'] ?? null) : null;

        return is_string($value) ? trim($value) : '';
    }

    private function url(Connection $connection, string $path): string
    {
        return rtrim((string) $connection->base_url, '/') . $path;
    }

    private function listPayload(mixed $payload, array $keys): array
    {
        if (! is_array($payload)) {
            return [];
        }

        foreach ($keys as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return $payload[$key];
            }
        }

        return $payload;
    }
}
