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
    private const PRODUCT_DISCOVERY_LIMIT = 10;
    private const VARIABLE_PARENT_DISCOVERY_LIMIT = 5;
    private const VARIATION_DISCOVERY_LIMIT = 10;
    private const SNAPSHOTS_TO_KEEP = 5;

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
            $message = $this->missingRequirementMessage($connection, $missing);

            return $this->storeSnapshot($connection, 'stores', 'failed', [
                'missing' => $this->friendlyMissingRequirements($connection, $missing),
            ], [], $message);
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
            $message = $this->missingRequirementMessage($connection, $missing);

            return $this->storeSnapshot($connection, 'products', 'failed', [
                'missing' => $this->friendlyMissingRequirements($connection, $missing),
            ], [], $message);
        }

        return $connection->type === 'woocommerce'
            ? $this->discoverWooProducts($connection)
            : $this->discoverFrontProducts($connection);
    }

    private function discoverWooProducts(Connection $connection): ConnectionDiscoverySnapshot
    {
        try {
            $response = $this->wooCommerce->products($connection, self::PRODUCT_DISCOVERY_LIMIT);

            if (! $response->successful()) {
                return $this->failedHttpSnapshot($connection, 'products', $response);
            }

            $products = $this->safeWooProducts($response->json());
            $variations = $this->discoverWooVariations($connection, $products);
            $readiness = $this->wooReadinessReport($products, $variations);

            return $this->storeSnapshot($connection, 'products', 'success', [
                'count' => count($products),
                'variation_count' => count($variations),
                'endpoint' => 'GET /wp-json/wc/v3/products',
                'limit' => self::PRODUCT_DISCOVERY_LIMIT,
                'read_only' => true,
                'variation_endpoint' => 'GET /wp-json/wc/v3/products/{productId}/variations',
                'variation_parent_limit' => self::VARIABLE_PARENT_DISCOVERY_LIMIT,
                'variation_limit_per_parent' => self::VARIATION_DISCOVERY_LIMIT,
                'readiness_ready' => $readiness['summary']['ready'],
                'readiness_needs_attention' => $readiness['summary']['needs_attention'],
                'readiness_blocked' => $readiness['summary']['blocked'],
            ], [
                'products' => $products,
                'variations' => $variations,
                'readiness' => $readiness,
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
            // Front's OpenAPI spec documents POST /api/Product as the read-only product listing/search endpoint.
            // Keep this capped for discovery and never confuse it with /api/products, the product CRUD endpoint.
            $response = $this->frontSystems->products($connection, self::PRODUCT_DISCOVERY_LIMIT);

            if (! $response->successful()) {
                return $this->failedHttpSnapshot($connection, 'products', $response);
            }

            $products = $this->safeFrontProducts($response->json());

            return $this->storeSnapshot($connection, 'products', 'success', [
                'count' => count($products),
                'endpoint' => 'POST /api/Product',
                'limit' => self::PRODUCT_DISCOVERY_LIMIT,
                'read_only' => true,
                'front_openapi_note' => 'Read-only product listing endpoint according to the Front OpenAPI spec. Do not confuse with /api/products CRUD.',
            ], [
                'products' => $products,
            ]);
        } catch (Throwable $exception) {
            return $this->storeSnapshot($connection, 'products', 'failed', [
                'endpoint' => 'POST /api/Product',
                'read_only' => true,
                'front_openapi_note' => 'Read-only product listing endpoint according to the Front OpenAPI spec. Do not confuse with /api/products CRUD.',
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
                    'image' => $this->safeFirstImage($product['images'] ?? []),
                    'variation_count' => is_array($variationIds) ? count($variationIds) : 0,
                    'gtin_candidate' => $this->gtinDetector->detect($product),
                ];
            })
            ->values()
            ->all();
    }

    private function discoverWooVariations(Connection $connection, array $products): array
    {
        $variableProducts = collect($products)
            ->filter(fn (array $product): bool => ($product['type'] ?? null) === 'variable')
            ->take(self::VARIABLE_PARENT_DISCOVERY_LIMIT);

        $variations = [];

        foreach ($variableProducts as $product) {
            $productId = (int) ($product['id'] ?? 0);

            if ($productId <= 0) {
                continue;
            }

            try {
                $response = $this->wooCommerce->variations($connection, $productId, self::VARIATION_DISCOVERY_LIMIT);

                if (! $response->successful()) {
                    $variations[] = [
                        'parent_id' => $productId,
                        'discovery_status' => 'failed',
                        'error' => 'HTTP ' . $response->status(),
                    ];

                    continue;
                }

                foreach ($this->safeWooVariations($response->json(), $product) as $variation) {
                    $variations[] = $variation;
                }
            } catch (Throwable $exception) {
                $variations[] = [
                    'parent_id' => $productId,
                    'discovery_status' => 'failed',
                    'error' => $exception::class,
                ];
            }
        }

        return $variations;
    }

    private function safeWooVariations(mixed $payload, array $parentProduct): array
    {
        $variations = is_array($payload) ? $payload : [];
        $parentId = $parentProduct['id'] ?? null;
        $parentName = $parentProduct['name'] ?? null;

        return collect($variations)
            ->filter(fn ($variation): bool => is_array($variation))
            ->take(self::VARIATION_DISCOVERY_LIMIT)
            ->map(function (array $variation) use ($parentId, $parentName): array {
                return [
                    'id' => $variation['id'] ?? null,
                    'parent_id' => $parentId,
                    'parent_name' => $parentName,
                    'name' => $variation['name'] ?? $this->variationName($parentName, $variation['attributes'] ?? []),
                    'sku' => $variation['sku'] ?? null,
                    'type' => 'variation',
                    'status' => $variation['status'] ?? null,
                    'permalink' => $variation['permalink'] ?? null,
                    'price' => $variation['price'] ?? null,
                    'regular_price' => $variation['regular_price'] ?? null,
                    'sale_price' => $variation['sale_price'] ?? null,
                    'stock_quantity' => $variation['stock_quantity'] ?? null,
                    'stock_status' => $variation['stock_status'] ?? null,
                    'manage_stock' => $variation['manage_stock'] ?? null,
                    'attributes' => $this->safeAttributeNames($variation['attributes'] ?? []),
                    'gtin_candidate' => $this->gtinDetector->detect($variation),
                    'discovery_status' => 'success',
                ];
            })
            ->values()
            ->all();
    }

    private function variationName(?string $parentName, mixed $attributes): ?string
    {
        $labels = $this->safeAttributeNames($attributes);

        if ($parentName && $labels !== []) {
            return $parentName . ' - ' . implode(' / ', $labels);
        }

        return $parentName;
    }

    private function safeAttributeNames(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        return collect($payload)
            ->filter(fn ($item): bool => is_array($item))
            ->take(10)
            ->map(fn (array $item): ?string => $item['option'] ?? $item['name'] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    private function safeFirstImage(mixed $payload): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        $image = collect($payload)->first(fn (mixed $item): bool => is_array($item));

        if (! is_array($image) || ! is_scalar($image['src'] ?? null)) {
            return null;
        }

        $src = trim((string) $image['src']);

        if ($src === '') {
            return null;
        }

        return [
            'src' => $src,
            'alt' => is_scalar($image['alt'] ?? null) ? trim((string) $image['alt']) : null,
        ];
    }

    private function wooReadinessReport(array $products, array $variations): array
    {
        $variationCountByParent = collect($variations)
            ->filter(fn (array $variation): bool => ($variation['discovery_status'] ?? null) === 'success')
            ->countBy(fn (array $variation): string => (string) ($variation['parent_id'] ?? ''));

        $rows = [];

        foreach ($products as $product) {
            if (($product['type'] ?? null) === 'variable') {
                $rows[] = $this->readinessRow($product, 'product', [
                    'warnings' => ['Variable parent detected. Review variation rows before any future sync.'],
                    'errors' => ((int) ($variationCountByParent[(string) ($product['id'] ?? '')] ?? 0)) === 0
                        ? ['No variations discovered in this read-only sample.']
                        : [],
                ]);

                continue;
            }

            $rows[] = $this->readinessRow($product, 'product');
        }

        foreach ($variations as $variation) {
            if (($variation['discovery_status'] ?? null) !== 'success') {
                $rows[] = [
                    'item_type' => 'variation',
                    'woo_product_id' => $variation['parent_id'] ?? null,
                    'woo_variation_id' => null,
                    'name' => 'Variation discovery failed',
                    'sku' => null,
                    'gtin' => null,
                    'gtin_key' => null,
                    'price' => null,
                    'status' => 'blocked',
                    'errors' => [$variation['error'] ?? 'Variation discovery failed.'],
                    'warnings' => [],
                ];

                continue;
            }

            $rows[] = $this->readinessRow($variation, 'variation');
        }

        $summary = [
            'ready' => collect($rows)->where('status', 'ready')->count(),
            'needs_attention' => collect($rows)->where('status', 'needs_attention')->count(),
            'blocked' => collect($rows)->where('status', 'blocked')->count(),
            'total_rows' => count($rows),
        ];

        return [
            'summary' => $summary,
            'rows' => $rows,
        ];
    }

    private function readinessRow(array $item, string $itemType, array $extra = []): array
    {
        $errors = $extra['errors'] ?? [];
        $warnings = $extra['warnings'] ?? [];
        $gtin = $item['gtin_candidate']['value'] ?? null;
        $price = ($item['regular_price'] ?? null) ?: ($item['price'] ?? null);

        if (! ($item['name'] ?? null)) {
            $errors[] = 'Missing product name.';
        }

        if (! ($item['sku'] ?? null)) {
            $errors[] = 'Missing SKU.';
        }

        if (! $gtin) {
            $errors[] = 'Missing GTIN/EAN candidate.';
        }

        if (! $price) {
            $errors[] = 'Missing price candidate.';
        }

        if (! in_array(($item['type'] ?? null), ['simple', 'variable', 'variation'], true)) {
            $errors[] = 'Unsupported product type.';
        }

        if (($item['stock_status'] ?? null) !== 'instock') {
            $warnings[] = 'Not currently in stock.';
        }

        if (($item['manage_stock'] ?? null) === false) {
            $warnings[] = 'Stock is not managed on this WooCommerce item.';
        }

        if (($item['gtin_candidate']['confidence'] ?? 'none') !== 'exact_known_field') {
            $warnings[] = 'GTIN/EAN field should be confirmed.';
        }

        $status = $errors !== []
            ? 'blocked'
            : ($warnings !== [] ? 'needs_attention' : 'ready');

        return [
            'item_type' => $itemType,
            'woo_product_id' => $itemType === 'variation' ? ($item['parent_id'] ?? null) : ($item['id'] ?? null),
            'woo_variation_id' => $itemType === 'variation' ? ($item['id'] ?? null) : null,
            'name' => $item['name'] ?? null,
            'sku' => $item['sku'] ?? null,
            'gtin' => $gtin,
            'gtin_key' => $item['gtin_candidate']['key'] ?? null,
            'price' => $price,
            'stock_status' => $item['stock_status'] ?? null,
            'status' => $status,
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
        ];
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
            ->limit(self::SNAPSHOTS_TO_KEEP)
            ->pluck('id');

        ConnectionDiscoverySnapshot::query()
            ->where('connection_id', $connection->id)
            ->where('discovery_type', $type)
            ->whereNotIn('id', $idsToKeep)
            ->delete();
    }

    private function missingRequirementMessage(Connection $connection, array $missing): string
    {
        return 'Missing required settings: ' . implode(', ', $this->friendlyMissingRequirements($connection, $missing));
    }

    private function friendlyMissingRequirements(Connection $connection, array $missing): array
    {
        return collect($missing)
            ->map(fn (string $requirement): string => $this->friendlyMissingRequirement($connection, $requirement))
            ->values()
            ->all();
    }

    private function friendlyMissingRequirement(Connection $connection, string $requirement): string
    {
        if ($connection->type === 'woocommerce') {
            return match ($requirement) {
                'base_url' => 'Missing WooCommerce site URL',
                'credential:consumer_key' => 'Missing WooCommerce consumer key',
                'credential:consumer_secret' => 'Missing WooCommerce consumer secret',
                default => $requirement,
            };
        }

        if (in_array($connection->type, ['front', 'front_systems'], true)) {
            return match ($requirement) {
                'base_url' => 'Missing Front base URL',
                'credential:api_key' => 'Missing Front API key',
                default => $requirement,
            };
        }

        return $requirement;
    }
}
