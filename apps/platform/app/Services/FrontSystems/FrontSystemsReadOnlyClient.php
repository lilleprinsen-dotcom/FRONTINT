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
        $startedAt = microtime(true);

        try {
            $response = $this->environment($connection);
            $metadata = [];

            if ($response->successful()) {
                $storesResponse = $this->stores($connection);

                if ($storesResponse->successful()) {
                    $metadata['front_stores'] = $this->safeStoreMetadata($storesResponse->json());
                    $metadata['stores_http_status'] = $storesResponse->status();
                } else {
                    $metadata['stores_http_status'] = $storesResponse->status();
                    $metadata['stores_check_status'] = 'failed';
                }
            }

            return [
                'status' => $response->successful() ? 'success' : 'failed',
                'message' => $response->successful()
                    ? 'Front Systems REST API responded to a read-only environment check.'
                    : 'Front Systems REST API responded with a non-success status.',
                'service' => 'front_systems',
                'operation' => 'GET /api/Environment',
                'http_status' => $response->status(),
                'http_checked' => true,
                'read_only' => true,
                'response_time_ms' => $this->responseTimeMs($startedAt),
                'checked_at' => now()->toISOString(),
                'last_error' => $response->successful() ? null : 'HTTP ' . $response->status(),
                'metadata' => $metadata,
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'failed',
                'message' => 'Front Systems REST API could not be reached by the read-only check.',
                'service' => 'front_systems',
                'operation' => 'GET /api/Environment',
                'error_class' => $exception::class,
                'http_checked' => true,
                'read_only' => true,
                'response_time_ms' => $this->responseTimeMs($startedAt),
                'checked_at' => now()->toISOString(),
                'last_error' => $exception::class,
                'metadata' => [],
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

    public function webhookTypes(Connection $connection): Response
    {
        return $this->request($connection)
            ->get($this->url($connection, '/api/WebhooksTypes'));
    }

    public function stockSettings(Connection $connection): Response
    {
        return $this->request($connection)
            ->get($this->url($connection, '/api/Stock/settings'));
    }

    public function stockList(Connection $connection): Response
    {
        return $this->request($connection)
            ->get($this->url($connection, '/api/Stock/list'));
    }

    public function products(Connection $connection, int $limit = 10): Response
    {
        // Front's OpenAPI spec uses POST /api/Product for read-only product listing/search.
        // Discovery must keep pageSize <= 10 and must not use /api/products, which is product CRUD.
        return $this->request($connection)
            ->post($this->url($connection, '/api/Product'), [
                'pageSize' => min(max($limit, 1), 10),
                'pageSkip' => 0,
                'isWebAvailable' => true,
                'isDiscontinued' => false,
                'excludeDeleted' => true,
                'includeEmptyGTINs' => false,
                'includeStockQuantity' => false,
                'includeAlternativeIdentifiers' => true,
            ]);
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

    public function safeStoreMetadata(mixed $payload): array
    {
        $stores = is_array($payload) ? $payload : [];

        if (array_key_exists('stores', $stores) && is_array($stores['stores'])) {
            $stores = $stores['stores'];
        }

        return collect($stores)
            ->filter(fn ($store): bool => is_array($store))
            ->map(fn (array $store): array => [
                'store_id' => $store['StoreId'] ?? $store['storeId'] ?? $store['id'] ?? null,
                'store_no' => $store['StoreNo'] ?? $store['storeNo'] ?? null,
                'store_name' => $store['StoreName'] ?? $store['storeName'] ?? $store['name'] ?? null,
                'stock_id' => $store['StockId'] ?? $store['stockId'] ?? null,
                'external_stock_id' => $store['ExternalStockId'] ?? $store['externalStockId'] ?? null,
                'currency' => $store['Currency'] ?? $store['currency'] ?? null,
                'time_zone' => $store['TimeZoneInfo'] ?? $store['TimeZone'] ?? $store['timeZone'] ?? null,
            ])
            ->filter(fn (array $store): bool => array_filter($store) !== [])
            ->values()
            ->all();
    }

    public function safeWebhookTypes(mixed $payload): array
    {
        return collect($this->listPayload($payload, ['webhookTypes', 'WebhookTypes', 'types', 'Types', 'items', 'Items']))
            ->filter(fn ($type): bool => is_scalar($type) || is_array($type))
            ->take(50)
            ->map(function (mixed $type): array {
                if (is_scalar($type)) {
                    return [
                        'type' => trim((string) $type),
                        'description' => null,
                    ];
                }

                return [
                    'type' => $type['webhookType'] ?? $type['WebhookType'] ?? $type['type'] ?? $type['Type'] ?? $type['name'] ?? $type['Name'] ?? null,
                    'description' => $type['description'] ?? $type['Description'] ?? null,
                ];
            })
            ->filter(fn (array $type): bool => is_string($type['type'] ?? null) && trim($type['type']) !== '')
            ->values()
            ->all();
    }

    public function safeStockSettings(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $settings = $payload['settings'] ?? $payload['Settings'] ?? $payload;

        if (! is_array($settings)) {
            return [];
        }

        return collect($settings)
            ->filter(fn (mixed $value, string|int $key): bool => ! $this->looksSensitiveKey((string) $key) && (is_scalar($value) || $value === null))
            ->mapWithKeys(fn (mixed $value, string|int $key): array => [(string) $key => is_string($value) ? trim($value) : $value])
            ->take(30)
            ->all();
    }

    private function looksSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        foreach (['secret', 'token', 'password', 'apikey', 'api_key', 'api-key', 'authorization', 'cookie'] as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }

    public function safeStockList(mixed $payload): array
    {
        return collect($this->listPayload($payload, ['stocks', 'Stocks', 'items', 'Items', 'stockList', 'StockList']))
            ->filter(fn ($stock): bool => is_array($stock))
            ->take(30)
            ->map(fn (array $stock): array => [
                'stock_id' => $stock['StockId'] ?? $stock['stockId'] ?? $stock['id'] ?? $stock['Id'] ?? null,
                'external_stock_id' => $stock['ExternalStockId'] ?? $stock['externalStockId'] ?? $stock['stockExtId'] ?? $stock['StockExtId'] ?? null,
                'name' => $stock['Name'] ?? $stock['name'] ?? $stock['StockName'] ?? $stock['stockName'] ?? null,
                'store_id' => $stock['StoreId'] ?? $stock['storeId'] ?? null,
            ])
            ->filter(fn (array $stock): bool => array_filter($stock) !== [])
            ->values()
            ->all();
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

    private function responseTimeMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
