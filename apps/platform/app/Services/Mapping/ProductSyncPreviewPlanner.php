<?php

namespace App\Services\Mapping;

use App\Models\ConnectionDiscoverySnapshot;
use App\Models\ProductSyncPreviewPlan;
use App\Models\User;
use Illuminate\Support\Collection;

class ProductSyncPreviewPlanner
{
    public const MAX_SELECTED_PRODUCTS = 10;

    public function createPlan(
        User $user,
        ConnectionDiscoverySnapshot $wooSnapshot,
        ?ConnectionDiscoverySnapshot $frontSnapshot,
        array $selectedWooItemKeys,
    ): ProductSyncPreviewPlan {
        $selectedKeys = collect($selectedWooItemKeys)
            ->map(fn (mixed $key): string => (string) $key)
            ->take(self::MAX_SELECTED_PRODUCTS)
            ->values();

        $wooItems = $this->wooItemsFromSnapshot($wooSnapshot)
            ->filter(fn (array $item): bool => $selectedKeys->contains($this->wooItemKey($item)))
            ->values();

        $frontProducts = collect($frontSnapshot?->sample_json['products'] ?? [])
            ->filter(fn ($product): bool => is_array($product))
            ->values()
            ->all();

        $duplicateGtins = $this->duplicateValues($wooItems, fn (array $item): ?string => $this->gtinValue($item));
        $duplicateSkus = $this->duplicateValues($wooItems, fn (array $item): ?string => $this->stringValue($item['sku'] ?? null));
        $frontSizes = $this->flattenFrontSizes($frontProducts);

        $rows = $wooItems
            ->map(fn (array $item): array => $this->planRow($item, $frontSizes, $duplicateGtins, $duplicateSkus))
            ->values()
            ->all();

        $blockedCount = collect($rows)->where('status', 'blocked')->count();
        $readyCount = count($rows) - $blockedCount;
        $status = $blockedCount > 0 || count($rows) === 0 ? 'blocked' : 'ready';

        return ProductSyncPreviewPlan::query()->create([
            'organization_id' => $wooSnapshot->organization_id,
            'created_by_user_id' => $user->id,
            'woo_connection_id' => $wooSnapshot->connection_id,
            'front_connection_id' => $frontSnapshot?->connection_id,
            'status' => $status,
            'selected_count' => count($rows),
            'summary_json' => [
                'selected_count' => count($rows),
                'ready_count' => $readyCount,
                'blocked_count' => $blockedCount,
                'max_selected_products' => self::MAX_SELECTED_PRODUCTS,
                'preview_only' => true,
                'external_api_calls' => false,
                'writes_performed' => false,
                'front_sample_available' => $frontSnapshot !== null,
            ],
            'plan_json' => [
                'rows' => $rows,
            ],
            'validation_json' => [
                'rows' => collect($rows)
                    ->map(fn (array $row): array => [
                        'woo_item_key' => $row['woo_product']['item_key'] ?? null,
                        'woo_product_id' => $row['woo_product']['parent_product_id'] ?? $row['woo_product']['id'] ?? null,
                        'woo_variation_id' => $row['woo_product']['type'] === 'variation' ? ($row['woo_product']['id'] ?? null) : null,
                        'status' => $row['status'],
                        'blocks' => $row['blocks'],
                        'warnings' => $row['warnings'],
                        'needs_confirmation' => $row['needs_confirmation'],
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function previewRows(array $wooProducts, array $frontProducts): array
    {
        $wooCollection = collect($wooProducts)->filter(fn ($product): bool => is_array($product))->values();
        $duplicateGtins = $this->duplicateValues($wooCollection, fn (array $product): ?string => $this->gtinValue($product));
        $duplicateSkus = $this->duplicateValues($wooCollection, fn (array $product): ?string => $this->stringValue($product['sku'] ?? null));
        $frontSizes = $this->flattenFrontSizes($frontProducts);

        return $wooCollection
            ->map(fn (array $product): array => $this->planRow($product, $frontSizes, $duplicateGtins, $duplicateSkus))
            ->values()
            ->all();
    }

    public function wooItemsFromSnapshot(ConnectionDiscoverySnapshot $snapshot): Collection
    {
        $products = collect($snapshot->sample_json['products'] ?? [])
            ->filter(fn ($product): bool => is_array($product))
            ->map(fn (array $product): array => $this->normalizeWooProductItem($product));
        $parentsById = $products
            ->filter(fn (array $product): bool => ($product['id'] ?? null) !== null)
            ->keyBy(fn (array $product): string => (string) $product['id']);

        $variations = collect($snapshot->sample_json['variations'] ?? [])
            ->filter(fn ($variation): bool => is_array($variation))
            ->filter(fn (array $variation): bool => ($variation['discovery_status'] ?? 'success') === 'success')
            ->map(fn (array $variation): array => $this->normalizeWooVariationItem(
                $variation,
                $parentsById->get((string) ($variation['parent_id'] ?? '')),
            ));

        return $products
            ->concat($variations)
            ->filter(fn (array $item): bool => $this->wooItemKey($item) !== '')
            ->values();
    }

    public function wooItemKey(array $item): string
    {
        if (($item['type'] ?? null) === 'variation') {
            return 'variation:' . (string) ($item['id'] ?? '');
        }

        return 'product:' . (string) ($item['id'] ?? '');
    }

    private function planRow(array $wooProduct, array $frontSizes, array $duplicateGtins, array $duplicateSkus): array
    {
        $gtin = $this->gtinValue($wooProduct);
        $sku = $this->stringValue($wooProduct['sku'] ?? null);
        $name = $this->stringValue($wooProduct['name'] ?? null);
        $frontName = (($wooProduct['type'] ?? null) === 'variation')
            ? ($this->stringValue($wooProduct['parent_name'] ?? null) ?? $name)
            : $name;
        $sizeLabel = (($wooProduct['type'] ?? null) === 'variation')
            ? $this->variationSizeLabel($wooProduct)
            : null;
        $price = $this->priceCandidate($wooProduct);
        $brand = $this->firstString($wooProduct['brands'] ?? []);
        $category = $this->firstString($wooProduct['categories'] ?? []);
        $subcategory = $this->secondString($wooProduct['categories'] ?? []);
        $frontMatch = $this->matchFrontSize($gtin, $sku, $frontSizes);
        $blocks = [];
        $warnings = [];
        $needsConfirmation = [
            'groupName/subgroupName mapping',
            'brand source',
            'size label',
            'product number/variant strategy',
            'whether Woo SKU or GTIN is primary identifier',
        ];

        if (($this->stringValue($wooProduct['sale_price'] ?? null)) !== null) {
            $needsConfirmation[] = 'whether sale price should be sent to PriceListV2 later';
        }

        if ($name === null) {
            $blocks[] = 'Missing product name.';
        }

        if ($sku === null) {
            $blocks[] = 'Missing SKU.';
        }

        if ($sku === null && $gtin === null) {
            $blocks[] = 'Missing both SKU and GTIN/EAN candidate.';
        } elseif ($gtin === null) {
            $warnings[] = 'Missing GTIN/EAN candidate; SKU fallback may be used if the SKU is unique and approved.';
        }

        if ($gtin !== null && in_array($gtin, $duplicateGtins, true)) {
            $blocks[] = 'Duplicate GTIN/EAN within selected sample.';
        }

        if ($sku !== null && in_array($sku, $duplicateSkus, true)) {
            $blocks[] = 'Duplicate SKU within selected sample.';
        }

        if (($wooProduct['type'] ?? null) === 'variable') {
            $warnings[] = 'Variable parent selected. Usually the sellable variation rows should be selected instead.';
        }

        if (($wooProduct['type'] ?? null) === 'variation' && ($wooProduct['parent_product_id'] ?? null) === null) {
            $blocks[] = 'Variation is missing parent product context.';
        }

        if ($price === null) {
            $blocks[] = 'No price candidate exists.';
        }

        if ($brand === null) {
            $warnings[] = 'Missing brand.';
        }

        if ($category === null) {
            $warnings[] = 'Missing category.';
        } else {
            $warnings[] = 'Category mapping is uncertain.';
        }

        if ($brand !== null) {
            $warnings[] = 'Brand mapping is uncertain.';
        }

        if (($this->stringValue($wooProduct['sale_price'] ?? null)) === null) {
            $warnings[] = 'Missing sale price.';
        }

        if (($wooProduct['stock_status'] ?? null) === 'outofstock') {
            $warnings[] = 'Stock is out of stock.';
        }

        if (($wooProduct['manage_stock'] ?? null) === false) {
            $warnings[] = 'manage_stock=false.';
        }

        if (($wooProduct['gtin_candidate']['candidates'] ?? []) !== [] && count($wooProduct['gtin_candidate']['candidates']) > 1) {
            $warnings[] = 'Multiple GTIN/EAN candidates found; confirm the correct field before syncing.';
        }

        if ($frontMatch['status'] === 'front_sample_missing') {
            $warnings[] = 'Front product sample is missing; existing Front match could not be checked.';
        }

        if ($frontMatch['status'] === 'no_match') {
            $warnings[] = 'No Front match found in current Front sample.';
        }

        if ($frontMatch['status'] === 'possible_duplicate') {
            $warnings[] = 'Possible duplicate: SKU matched a Front size with a different GTIN.';
        }

        $status = $blocks === [] ? 'ready' : 'blocked';

        return [
            'woo_product' => [
                'id' => $wooProduct['id'] ?? null,
                'item_key' => $this->wooItemKey($wooProduct),
                'parent_product_id' => $wooProduct['parent_product_id'] ?? null,
                'name' => $name,
                'parent_name' => $wooProduct['parent_name'] ?? null,
                'sku' => $sku,
                'type' => $wooProduct['type'] ?? null,
                'stock_status' => $wooProduct['stock_status'] ?? null,
                'manage_stock' => $wooProduct['manage_stock'] ?? null,
                'size_label' => $sizeLabel,
            ],
            'gtin_candidate' => $wooProduct['gtin_candidate'] ?? ['key' => null, 'value' => null, 'confidence' => 'none', 'candidates' => []],
            'front_match' => $frontMatch,
            'proposed_front_payload' => [
                'name' => $frontName,
                'number' => $sku,
                'variant' => $sizeLabel ?? $sku,
                'brand' => $brand,
                'groupName' => $category,
                'subgroupName' => $subcategory,
                'price_candidate' => $price,
                'sale_price_candidate' => $this->stringValue($wooProduct['sale_price'] ?? null),
                'image_candidate' => $wooProduct['image'] ?? null,
                'productSizes' => [
                    [
                        'gtin' => $gtin,
                        'externalSKU' => $sku,
                        'label' => $sizeLabel,
                    ],
                ],
            ],
            'status' => $status,
            'blocks' => $blocks,
            'warnings' => array_values(array_unique($warnings)),
            'needs_confirmation' => array_values(array_unique($needsConfirmation)),
            'preview_only' => true,
        ];
    }

    private function normalizeWooProductItem(array $product): array
    {
        $product['type'] = $product['type'] ?? 'simple';
        $product['item_key'] = $this->wooItemKey($product);
        $product['image'] = $this->firstImage($product);

        return $product;
    }

    private function normalizeWooVariationItem(array $variation, ?array $parentProduct = null): array
    {
        $attributes = $variation['attributes'] ?? [];
        $sizeLabel = $this->attributeLabel($attributes);
        $parentName = $this->stringValue($variation['parent_name'] ?? null)
            ?? $this->stringValue($parentProduct['name'] ?? null);
        $rawName = $this->stringValue($variation['name'] ?? null);
        $displayName = $this->variationDisplayName($parentName, $rawName, $sizeLabel);

        return [
            'id' => $variation['id'] ?? null,
            'parent_product_id' => $variation['parent_id'] ?? null,
            'parent_name' => $parentName,
            'name' => $displayName,
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
            'categories' => $variation['categories'] ?? $parentProduct['categories'] ?? [],
            'brands' => $variation['brands'] ?? $parentProduct['brands'] ?? [],
            'image' => $this->firstImage($variation) ?? $this->firstImage($parentProduct ?? []),
            'attributes' => $attributes,
            'size_label' => $sizeLabel,
            'gtin_candidate' => $variation['gtin_candidate'] ?? ['key' => null, 'value' => null, 'confidence' => 'none', 'candidates' => []],
            'item_key' => 'variation:' . (string) ($variation['id'] ?? ''),
        ];
    }

    private function duplicateValues(Collection $products, callable $valueResolver): array
    {
        return $products
            ->map($valueResolver)
            ->filter(fn (?string $value): bool => $value !== null)
            ->countBy()
            ->filter(fn (int $count): bool => $count > 1)
            ->keys()
            ->map(fn (mixed $value): string => (string) $value)
            ->values()
            ->all();
    }

    private function matchFrontSize(?string $gtin, ?string $sku, array $frontSizes): array
    {
        if ($frontSizes === []) {
            return [
                'status' => 'front_sample_missing',
                'productid' => null,
                'name' => null,
                'gtin' => null,
                'identity' => null,
                'external_sku' => null,
                'method' => 'none',
                'confidence' => 'none',
            ];
        }

        if ($gtin !== null) {
            $match = collect($frontSizes)->first(fn (array $size): bool => (string) ($size['gtin'] ?? '') === $gtin);

            if ($match) {
                return $this->frontMatch('matched_existing_front_product', $match, 'gtin', 'high');
            }
        }

        if ($sku !== null) {
            $match = collect($frontSizes)->first(fn (array $size): bool => (string) ($size['external_sku'] ?? '') === $sku);

            if ($match) {
                $status = $gtin !== null && ($match['gtin'] ?? null) && (string) $match['gtin'] !== $gtin
                    ? 'possible_duplicate'
                    : 'matched_existing_front_product';

                return $this->frontMatch($status, $match, 'sku_external_sku', 'medium');
            }
        }

        if ($sku !== null) {
            $match = collect($frontSizes)->first(fn (array $size): bool => (string) ($size['identity'] ?? '') === $sku);

            if ($match) {
                $status = $gtin !== null && ($match['gtin'] ?? null) && (string) $match['gtin'] !== $gtin
                    ? 'possible_duplicate'
                    : 'matched_existing_front_product';

                return $this->frontMatch($status, $match, 'sku_identity', 'medium');
            }
        }

        return [
            'status' => 'no_match',
            'productid' => null,
            'name' => null,
            'gtin' => null,
            'identity' => null,
            'external_sku' => null,
            'method' => 'none',
            'confidence' => 'none',
        ];
    }

    private function frontMatch(string $status, array $match, string $method, string $confidence): array
    {
        return [
            'status' => $status,
            'productid' => $match['productid'] ?? null,
            'name' => $match['name'] ?? null,
            'gtin' => $match['gtin'] ?? null,
            'identity' => $match['identity'] ?? null,
            'external_sku' => $match['external_sku'] ?? null,
            'method' => $method,
            'confidence' => $confidence,
        ];
    }

    private function flattenFrontSizes(array $frontProducts): array
    {
        return collect($frontProducts)
            ->filter(fn ($product): bool => is_array($product))
            ->flatMap(function (array $product): array {
                $sizes = $product['productSizes'] ?? [];

                return collect(is_array($sizes) ? $sizes : [])
                    ->filter(fn ($size): bool => is_array($size))
                    ->map(fn (array $size): array => [
                        'productid' => $product['productid'] ?? null,
                        'name' => $product['name'] ?? null,
                        'gtin' => $this->stringValue($size['gtin'] ?? null),
                        'identity' => $this->stringValue($size['identity'] ?? null),
                        'external_sku' => $this->stringValue($size['externalSKU'] ?? null),
                    ])
                    ->all();
            })
            ->values()
            ->all();
    }

    private function gtinValue(array $product): ?string
    {
        return $this->stringValue($product['gtin_candidate']['value'] ?? null);
    }

    private function priceCandidate(array $product): ?string
    {
        return $this->stringValue($product['regular_price'] ?? null)
            ?? $this->stringValue($product['price'] ?? null);
    }

    private function firstString(mixed $value): ?string
    {
        return is_array($value) ? $this->stringValue($value[0] ?? null) : null;
    }

    private function secondString(mixed $value): ?string
    {
        return is_array($value) ? $this->stringValue($value[1] ?? null) : null;
    }

    private function variationSizeLabel(array $product): ?string
    {
        return $this->stringValue($product['size_label'] ?? null)
            ?? $this->attributeLabel($product['attributes'] ?? []);
    }

    private function attributeLabel(mixed $attributes): ?string
    {
        if (! is_array($attributes)) {
            return null;
        }

        $labels = collect($attributes)
            ->map(function (mixed $attribute): ?string {
                if (is_array($attribute)) {
                    return $this->stringValue($attribute['option'] ?? null)
                        ?? $this->stringValue($attribute['name'] ?? null);
                }

                return $this->stringValue($attribute);
            })
            ->filter()
            ->values()
            ->all();

        return $labels === [] ? null : implode(' / ', $labels);
    }

    private function variationDisplayName(?string $parentName, ?string $rawName, ?string $sizeLabel): ?string
    {
        if ($parentName !== null && $sizeLabel !== null) {
            return $parentName . ' - ' . $sizeLabel;
        }

        return $parentName ?? $rawName;
    }

    private function firstImage(array $item): ?array
    {
        $image = $item['image'] ?? null;

        if (is_array($image)) {
            $src = $this->stringValue($image['src'] ?? null);

            if ($src === null) {
                return null;
            }

            return [
                'src' => $src,
                'alt' => $this->stringValue($image['alt'] ?? null),
            ];
        }

        $images = $item['images'] ?? [];

        if (! is_array($images)) {
            return null;
        }

        $first = collect($images)->first(fn (mixed $candidate): bool => is_array($candidate));

        if (! is_array($first)) {
            return null;
        }

        $src = $this->stringValue($first['src'] ?? null);

        if ($src === null) {
            return null;
        }

        return [
            'src' => $src,
            'alt' => $this->stringValue($first['alt'] ?? null),
        ];
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
