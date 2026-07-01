<?php

namespace App\Services\FrontSystems;

use App\Models\Connection;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class FrontSystemsProductWriteClient
{
    public function createProduct(Connection $connection, array $payload): Response
    {
        return Http::timeout(15)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'x-api-key' => $this->apiKey($connection),
            ])
            ->post($this->url($connection, '/api/products'), $payload);
    }

    public function updateProduct(Connection $connection, string $productIdOrExtId, array $payload): Response
    {
        return Http::timeout(15)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'x-api-key' => $this->apiKey($connection),
            ])
            ->put($this->url($connection, '/api/products/' . rawurlencode($productIdOrExtId)), $payload);
    }

    public function getProduct(Connection $connection, string $productIdOrExtId): Response
    {
        return Http::timeout(10)
            ->acceptJson()
            ->withHeaders([
                'x-api-key' => $this->apiKey($connection),
            ])
            ->get($this->url($connection, '/api/products/' . rawurlencode($productIdOrExtId)));
    }

    public function getProductByGtin(Connection $connection, string $gtin): Response
    {
        return Http::timeout(10)
            ->acceptJson()
            ->withHeaders([
                'x-api-key' => $this->apiKey($connection),
            ])
            ->get($this->url($connection, '/api/Product/gtin/' . rawurlencode($gtin)));
    }

    public function hasApiKey(Connection $connection): bool
    {
        return $this->apiKey($connection) !== '';
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
}
