<?php

namespace App\Services\WooCommerce;

use App\Models\Connection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class WooCommerceOrderWriteClient
{
    public function createOrder(Connection $connection, array $payload): Response
    {
        return $this->request($connection)
            ->post($this->url($connection, '/wp-json/wc/v3/orders'), $payload);
    }

    public function hasCredentials(Connection $connection): bool
    {
        return $this->credentialValue($connection, 'consumer_key') !== null
            && $this->credentialValue($connection, 'consumer_secret') !== null;
    }

    private function request(Connection $connection): PendingRequest
    {
        return Http::timeout(20)
            ->acceptJson()
            ->asJson()
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
