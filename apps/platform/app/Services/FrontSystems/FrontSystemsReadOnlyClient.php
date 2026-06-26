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

    public function products(Connection $connection, int $limit = 10): Response
    {
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

    private function responseTimeMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
