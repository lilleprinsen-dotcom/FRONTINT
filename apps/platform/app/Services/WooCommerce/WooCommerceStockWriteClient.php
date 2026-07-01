<?php

namespace App\Services\WooCommerce;

use App\Models\Connection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class WooCommerceStockWriteClient
{
    public function getProduct(Connection $connection, int $productId): Response
    {
        return $this->request($connection)
            ->get($this->url($connection, "/wp-json/wc/v3/products/{$productId}"));
    }

    public function updateProductStock(Connection $connection, int $productId, int $stockQuantity): Response
    {
        return $this->request($connection)
            ->put($this->url($connection, "/wp-json/wc/v3/products/{$productId}"), [
                'manage_stock' => true,
                'stock_quantity' => $stockQuantity,
            ]);
    }

    public function getVariation(Connection $connection, int $productId, int $variationId): Response
    {
        return $this->request($connection)
            ->get($this->url($connection, "/wp-json/wc/v3/products/{$productId}/variations/{$variationId}"));
    }

    public function updateVariationStock(Connection $connection, int $productId, int $variationId, int $stockQuantity): Response
    {
        return $this->request($connection)
            ->put($this->url($connection, "/wp-json/wc/v3/products/{$productId}/variations/{$variationId}"), [
                'manage_stock' => true,
                'stock_quantity' => $stockQuantity,
            ]);
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
