<?php

namespace App\Services\Discovery;

use App\Models\Connection;
use App\Models\ConnectionDiscoverySnapshot;
use App\Services\FrontSystems\FrontSystemsReadOnlyClient;
use App\Services\Safety\StagingSafety;
use App\Services\WooCommerce\WooCommerceReadOnlyClient;
use Illuminate\Http\Client\Response;
use Throwable;

class ConnectionDiscoveryService
{
    public function __construct(
        private readonly StagingSafety $safety,
        private readonly WooCommerceReadOnlyClient $wooCommerce,
        private readonly FrontSystemsReadOnlyClient $frontSystems,
        private readonly WooCommerceGtinCandidateDetector $gtinDetector,
    ) {
    }

    public function discoverStores(Connection $connection): ConnectionDiscoverySnapshot
    {
        $connection->loadMissing('credentials', 'organization');

        if (! in_array($connection->type, ['front', 'front_systems'], true)) {
            return $this->storeSnapshot($connection, 'stores', 'failed', [
                'message' => 'Store discovery is only available for Front Systems connections.',
            ], [], 'Unsupported connection type.');
        }

        if (! $this->safety->connectionHttpTestsAllowed()) {
            return $this->skippedSnapshot($connection, 'stores');
        }

        if ($missing = $this->missingRequirements($connection)) {
            return $this->storeSnapshot($connection, 'stores', 'failed', [
                'missing' => $missing,
            ], [], 'Missing required settings: ' . implode(', ', $missing));
        }

        try {
            $response = $this->frontSystems->stores($connection);

            if (! $response->successful()) {
                return $this->failedHttpSnapshot($connection, 'stores', $response);
            }

            $stores = $this->frontSystems->safeStoreMetadata($response->json());

            return $this->storeSnapshot($connection, 'stores', 'success', [
                'count' => count($stores),
                'endpoint' => 'GET /api/Stores',
                'read_only' => true,
            ], [
                'stores' => $stores,
            ]);
        } catch (Throwable $exception) {
            return $this->storeSnapshot($connection, 'stores', 'failed', [
                'endpoint' => 'GET /api/Stores',
                'read_only' => true,
            ], [], $exception::class);
        }
    }

    public function discoverProducts(Connection $connection): ConnectionDiscoverySnapshot
    {
        $connection->loadMissing('credentials', 'organization');

        if (! in_array($connection->type, ['woocommerce', 'front', 'front_systems'], true)) {
            return $this->storeSnapshot($connection, 'products', 'failed', [
                'message' => 'Product discovery is only available for WooCommerce and Front Systems connections.',
            ], [], 'Unsupported connection type.');
        }

        if (! $this->safety->connectionHttpTestsAllowed()) {
            return $this->skippedSnapshot($connection, 'products');
        }

        if ($missing = $this->missingRequirements($connection)) {
            return $this->storeSnapshot($connection, 'products', 'failed', [
                'missing' => $missing,
            ], [], 'Missing required settings: ' . implode(', ', $missing));
        }

        return $connection->type === 'woocommerce'
            ? $this->discoverWooProducts($connection)
            : $this->discoverFrontProducts($connection);
    }

    private function discoverWooProducts(Connection $connection): ConnectionDiscoverySnapshot
    {
        try {
            $response = $this->wooCommerce->products($connection);

            if (! $response->successful()) {
                return $this->failedHttpSnapshot($connection, 'products', $response);
            }

            $products = $this->safeWooProducts($response->json());

            return $this->storeSnapshot($connection, 'products', 'success', [
                'count' => count($products),
                'endpoint' => 'GET /wp-json/wc/v3/products',
                'limit' => 10,
                'read_only' => true,
                'variation_fetching' => 'TODO',
            ], [
                'products' => $products,
            ]);
        } catch (Throwable $exception) {
            return $this->storeSnapshot($connection, 'products', 'failed', [
                'endpoint' => 'GET /wp-json/wc/v3/products',
                'read_only' => true,
            ], [], $exception::class);
        }
    }

    private function discoverFrontProducts(Connection $connection): ConnectionDiscoverySnapshot
    {
        try {
            $response = $this->frontSystems->products($connection);

            if (! $response->successful()) {
                return $this->failedHttpSnapshot($connection, 'products', $response);
            }

            $products = $this->safeFrontProducts($response->json());

            return $this->storeSnapshot($connection, 'products', 'success', [
                'count' => count($products),
                'endpoint' => 'POST /api/Product',
                'limit' => 10,
                'read_only' => true,
            ], [
                'products' => $products,
            ]);
        } catch (Throwable $exception) {
            return $this->storeSnapshot($connection, 'products', 'failed', [
                'endpoint' => 'POST /api/Product',
                'read_only' => true,
            ], [], $exception::class);
        }
    }

    private function safeWooProducts(mixed $payload): array
    {
        $products = is_array($payload) ? $payload : [];

        return collect($products)
            ->filter(fn ($product): bool => is_array($product))
            ->take(10)
            ->map(function (array $product): array {
                $variationIds = $product['variations'] ?? [];

                return [
                    'id' => $product['id'] ?? null,
                    'name' => $product['name'] ?? null,
                    'sku' => $product['sku'] ?? null,
                    'type' => $product['type'] ?? null,
                    'status' => $product['status'] ?? null,
                    'permalink' => $product['permalink'] ?? null,
                    'price' => $product['price'] ?? null,
                    'regular_price' => $product['regular_price'] ?? null,
                    'sale_price' => $product['sale_price'] ?? null,
                    'stock_quantity' => $product['stock_quantity'] ?? null,
                    'stock_status' => $product['stock_status'] ?? null,
                    'manage_stock' => $product['manage_stock'] ?? null,
                    'categories' => $this->safeNames($product['categories'] ?? []),
                    'brands' => $this->safeNames($product['brands'] ?? []),
                    'variation_count' => is_array($variationIds) ? count($variationIds) : 0,
                    'gtin_candidate' => $this->gtinDetector->detect($product),
                ];
            })
            ->values()
            ->all();
    }

    private function safeFrontProducts(mixed $payload): array
    {
        $products = $this->frontProductList($payload);

        return collect($products)
            ->filter(fn ($product): bool => is_array($product))
            ->take(10)
            ->map(fn (array $product): array => [
                'productid' => $product['productid'] ?? $product['ProductId'] ?? $product['ProductID'] ?? $product['id'] ?? null,
                'name' => $product['name'] ?? $product['Name'] ?? null,
                'number' => $product['number'] ?? $product['Number'] ?? null,
                'variant' => $product['variant'] ?? $product['Variant'] ?? null,
                'brand' => $product['brand'] ?? $product['Brand'] ?? null,
                'groupName' => $product['groupName'] ?? $product['GroupName'] ?? null,
                'subgroupName' => $product['subgroupName'] ?? $product['SubgroupName'] ?? null,
                'isWebAvailable' => $product['isWebAvailable'] ?? $product['IsWebAvailable'] ?? null,
                'isDiscontinued' => $product['isDiscontinued'] ?? $product['IsDiscontinued'] ?? null,
                'productSizes' => $this->safeFrontProductSizes($product['productSizes'] ?? $product['ProductSizes'] ?? []),
            ])
            ->values()
            ->all();
    }

    private function frontProductList(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        foreach (['products', 'Products', 'items', 'Items', 'data', 'Data'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return $payload[$key];
            }
        }

        return $payload;
    }

    private function safeFrontProductSizes(mixed $payload): array
    {
        $sizes = is_array($payload) ? $payload : [];

        return collect($sizes)
            ->filter(fn ($size): bool => is_array($size))
            ->take(20)
            ->map(fn (array $size): array => [
                'identity' => $size['identity'] ?? $size['Identity'] ?? null,
                'gtin' => $size['gtin'] ?? $size['GTIN'] ?? $size['Gtin'] ?? null,
                'label' => $size['label'] ?? $size['Label'] ?? null,
                'externalSKU' => $size['externalSKU'] ?? $size['ExternalSKU'] ?? $size['externalSku'] ?? null,
                'identifiers' => $this->safeIdentifiers($size['identifiers'] ?? $size['Identifiers'] ?? []),
            ])
            ->values()
            ->all();
    }

    private function safeIdentifiers(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        return collect($payload)
            ->take(10)
            ->map(fn ($identifier): mixed => is_scalar($identifier) ? $identifier : $this->safeScalarMap($identifier))
            ->values()
            ->all();
    }

    private function safeNames(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        return collect($payload)
            ->filter(fn ($item): bool => is_array($item))
            ->take(10)
            ->map(fn (array $item): ?string => $item['name'] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    private function safeScalarMap(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        return collect($payload)
            ->filter(fn ($value): bool => is_scalar($value) || $value === null)
            ->map(fn ($value): mixed => is_string($value) ? trim($value) : $value)
            ->all();
    }

    private function skippedSnapshot(Connection $connection, string $type): ConnectionDiscoverySnapshot
    {
        return $this->storeSnapshot($connection, $type, 'skipped', [
            'message' => 'Safe mode: live read-only discovery is disabled.',
            'http_checked' => false,
            'read_only' => true,
        ]);
    }

    private function failedHttpSnapshot(Connection $connection, string $type, Response $response): ConnectionDiscoverySnapshot
    {
        return $this->storeSnapshot($connection, $type, 'failed', [
            'http_status' => $response->status(),
            'read_only' => true,
        ], [], 'HTTP ' . $response->status());
    }

    private function missingRequirements(Connection $connection): array
    {
        $missing = [];

        if (! $connection->base_url) {
            $missing[] = 'base_url';
        }

        foreach ($this->requiredCredentialTypes($connection->type) as $credentialType) {
            if (! $connection->credential($credentialType)) {
                $missing[] = "credential:{$credentialType}";
            }
        }

        return $missing;
    }

    private function requiredCredentialTypes(string $connectionType): array
    {
        return match ($connectionType) {
            'woocommerce' => ['consumer_key', 'consumer_secret'],
            'front', 'front_systems' => ['api_key'],
            default => [],
        };
    }

    private function storeSnapshot(
        Connection $connection,
        string $type,
        string $status,
        array $summary = [],
        array $sample = [],
        ?string $error = null,
    ): ConnectionDiscoverySnapshot {
        $snapshot = ConnectionDiscoverySnapshot::query()->create([
            'organization_id' => $connection->organization_id,
            'connection_id' => $connection->id,
            'source_system' => $connection->type,
            'discovery_type' => $type,
            'status' => $status,
            'summary_json' => $summary,
            'sample_json' => $sample,
            'error_message' => $error,
            'checked_at' => now(),
        ]);

        $this->pruneOldSnapshots($connection, $type);

        return $snapshot;
    }

    private function pruneOldSnapshots(Connection $connection, string $type): void
    {
        $idsToKeep = ConnectionDiscoverySnapshot::query()
            ->where('connection_id', $connection->id)
            ->where('discovery_type', $type)
            ->latest()
            ->limit(5)
            ->pluck('id');

        ConnectionDiscoverySnapshot::query()
            ->where('connection_id', $connection->id)
            ->where('discovery_type', $type)
            ->whereNotIn('id', $idsToKeep)
            ->delete();
    }
}
