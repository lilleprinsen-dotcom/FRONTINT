<?php

namespace App\Services\WooCommerce;

use App\Models\Connection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class WooCommerceReadOnlyClient
{
    public function test(Connection $connection): array
    {
        $startedAt = microtime(true);

        try {
            $response = $this->systemStatus($connection);

            return [
                'status' => $response->successful() ? 'success' : 'failed',
                'message' => $response->successful()
                    ? 'WooCommerce REST API responded to a read-only status check.'
                    : 'WooCommerce REST API responded with a non-success status.',
                'service' => 'woocommerce',
                'operation' => 'GET /wp-json/wc/v3/system_status',
                'http_status' => $response->status(),
                'http_checked' => true,
                'read_only' => true,
                'response_time_ms' => $this->responseTimeMs($startedAt),
                'checked_at' => now()->toISOString(),
                'last_error' => $response->successful() ? null : 'HTTP ' . $response->status(),
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'failed',
                'message' => 'WooCommerce REST API could not be reached by the read-only check.',
                'service' => 'woocommerce',
                'operation' => 'GET /wp-json/wc/v3/system_status',
                'error_class' => $exception::class,
                'http_checked' => true,
                'read_only' => true,
                'response_time_ms' => $this->responseTimeMs($startedAt),
                'checked_at' => now()->toISOString(),
                'last_error' => $exception::class,
            ];
        }
    }

    public function systemStatus(Connection $connection): Response
    {
        return $this->request($connection)
            ->get($this->url($connection, '/wp-json/wc/v3/system_status'));
    }

    public function products(Connection $connection, int $limit = 10): Response
    {
        return $this->request($connection)
            ->get($this->url($connection, '/wp-json/wc/v3/products'), [
                'per_page' => min(max($limit, 1), 10),
                'page' => 1,
                'status' => 'publish',
            ]);
    }

    public function variations(Connection $connection, int $productId, int $limit = 10): Response
    {
        return $this->request($connection)
            ->get($this->url($connection, "/wp-json/wc/v3/products/{$productId}/variations"), [
                'per_page' => min(max($limit, 1), 10),
                'page' => 1,
            ]);
    }

    private function request(Connection $connection): PendingRequest
    {
        return Http::timeout(10)
            ->acceptJson()
            ->withBasicAuth(
                $this->credentialValue($connection, 'consumer_key') ?? '',
                $this->credentialValue($connection, 'consumer_secret') ?? '',
            );
    }

    private function url(Connection $connection, string $path): string
    {
        return rtrim($this->siteUrl($connection), '/') . $path;
    }

    private function credentialValue(Connection $connection, string $type): ?string
    {
        $payload = $connection->credential($type)?->encrypted_payload;
        $value = is_array($payload) ? ($payload['value'] ?? null) : null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function siteUrl(Connection $connection): string
    {
        return (string) $connection->base_url;
    }

    private function responseTimeMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
