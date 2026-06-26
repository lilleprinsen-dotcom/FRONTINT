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
        try {
            $response = $this->systemStatus($connection);

            return [
                'status' => $response->successful() ? 'connected' : 'http_error',
                'message' => $response->successful()
                    ? 'WooCommerce REST API responded to a read-only status check.'
                    : 'WooCommerce REST API responded with a non-success status.',
                'service' => 'woocommerce',
                'operation' => 'GET /wp-json/wc/v3/system_status',
                'http_status' => $response->status(),
                'http_checked' => true,
                'read_only' => true,
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'unreachable',
                'message' => 'WooCommerce REST API could not be reached by the read-only check.',
                'service' => 'woocommerce',
                'operation' => 'GET /wp-json/wc/v3/system_status',
                'error_class' => $exception::class,
                'http_checked' => true,
                'read_only' => true,
            ];
        }
    }

    public function systemStatus(Connection $connection): Response
    {
        return $this->request($connection)
            ->get($this->url($connection, '/wp-json/wc/v3/system_status'));
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
        return rtrim((string) $connection->base_url, '/') . $path;
    }

    private function credentialValue(Connection $connection, string $type): ?string
    {
        $payload = $connection->credential($type)?->encrypted_payload;
        $value = is_array($payload) ? ($payload['value'] ?? null) : null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
