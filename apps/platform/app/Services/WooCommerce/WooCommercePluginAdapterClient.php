<?php

namespace App\Services\WooCommerce;

use App\Models\Connection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

class WooCommercePluginAdapterClient
{
    private const CONNECTION_TEST_ROUTE = '/omnibridge/v1/connection-test';

    public function test(Connection $connection): array
    {
        $connection->loadMissing('credentials');
        $startedAt = microtime(true);

        $missing = $this->missingRequirements($connection);

        if ($missing !== []) {
            return [
                'status' => 'failed',
                'message' => 'WooCommerce plugin adapter settings are incomplete.',
                'missing' => $missing,
                'http_checked' => false,
                'checked_at' => now()->toISOString(),
                'last_error' => 'Missing required settings: ' . implode(', ', $missing),
            ];
        }

        try {
            $timestamp = (string) time();
            $response = $this->request($connection, $timestamp)
                ->get($this->url($connection, self::CONNECTION_TEST_ROUTE));

            $body = $response->json();
            $confirmedReadOnly = is_array($body)
                && ($body['read_only'] ?? false) === true
                && ($body['writes_performed'] ?? true) === false;

            return [
                'status' => $response->successful() && $confirmedReadOnly ? 'success' : 'failed',
                'message' => $response->successful() && $confirmedReadOnly
                    ? 'WooCommerce OmniBridge plugin responded to a signed read-only adapter test.'
                    : 'WooCommerce OmniBridge plugin did not confirm a successful read-only adapter test.',
                'service' => 'woocommerce_plugin_adapter',
                'operation' => 'GET /wp-json/omnibridge/v1/connection-test',
                'http_status' => $response->status(),
                'http_checked' => true,
                'read_only' => true,
                'response_time_ms' => $this->responseTimeMs($startedAt),
                'checked_at' => now()->toISOString(),
                'last_error' => $response->successful() && $confirmedReadOnly ? null : 'HTTP ' . $response->status(),
                'metadata' => $this->safeMetadata($body),
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'failed',
                'message' => 'WooCommerce OmniBridge plugin could not be reached by the signed read-only adapter test.',
                'service' => 'woocommerce_plugin_adapter',
                'operation' => 'GET /wp-json/omnibridge/v1/connection-test',
                'error_class' => $exception::class,
                'http_checked' => true,
                'read_only' => true,
                'response_time_ms' => $this->responseTimeMs($startedAt),
                'checked_at' => now()->toISOString(),
                'last_error' => $exception::class,
            ];
        }
    }

    private function request(Connection $connection, string $timestamp): PendingRequest
    {
        return Http::timeout(10)
            ->acceptJson()
            ->withHeaders([
                'X-Omnibridge-Timestamp' => $timestamp,
                'X-Omnibridge-Signature' => $this->signature($connection, $timestamp),
            ]);
    }

    private function signature(Connection $connection, string $timestamp): string
    {
        return hash_hmac(
            'sha256',
            'GET' . "\n" . self::CONNECTION_TEST_ROUTE . "\n" . $timestamp,
            $this->credentialValue($connection, 'plugin_shared_secret') ?? '',
        );
    }

    private function url(Connection $connection, string $route): string
    {
        return rtrim((string) $connection->base_url, '/') . '/wp-json' . $route;
    }

    private function missingRequirements(Connection $connection): array
    {
        $missing = [];

        if (! $connection->base_url) {
            $missing[] = 'Missing WooCommerce site URL';
        }

        if (! $this->credentialValue($connection, 'plugin_shared_secret')) {
            $missing[] = 'Missing WooCommerce plugin shared secret';
        }

        return $missing;
    }

    private function credentialValue(Connection $connection, string $type): ?string
    {
        $payload = $connection->credential($type)?->encrypted_payload;
        $value = is_array($payload) ? ($payload['value'] ?? null) : null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function safeMetadata(mixed $body): ?array
    {
        if (! is_array($body)) {
            return null;
        }

        return [
            'plugin' => [
                'version' => data_get($body, 'plugin.version'),
                'environment' => data_get($body, 'plugin.environment'),
                'product_fields_enabled' => data_get($body, 'plugin.product_fields_enabled'),
            ],
            'woocommerce' => [
                'active' => data_get($body, 'woocommerce.active'),
                'version' => data_get($body, 'woocommerce.version'),
                'currency' => data_get($body, 'woocommerce.currency'),
            ],
            'capabilities' => [
                'signed_connection_test' => data_get($body, 'capabilities.signed_connection_test'),
                'product_sync_flags' => data_get($body, 'capabilities.product_sync_flags'),
                'catalog_scan_inside_plugin' => data_get($body, 'capabilities.catalog_scan_inside_plugin'),
                'sync_logic_inside_plugin' => data_get($body, 'capabilities.sync_logic_inside_plugin'),
            ],
        ];
    }

    private function responseTimeMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
