<?php

namespace App\Services\Readiness;

use App\Models\ConnectionDiscoverySnapshot;
use Illuminate\Support\Collection;

class WooProductReadinessSummary
{
    public function summarize(?ConnectionDiscoverySnapshot $snapshot): array
    {
        if (! $snapshot) {
            return [
                'has_snapshot' => false,
                'counts' => $this->emptyCounts(),
                'duplicates' => ['skus' => [], 'gtins' => []],
                'fixes' => [],
                'items' => [],
            ];
        }

        $items = $this->items($snapshot);
        $duplicates = [
            'skus' => $this->duplicates($items, 'sku'),
            'gtins' => $this->duplicates($items, 'gtin'),
        ];

        $items = $items
            ->map(fn (array $item): array => $this->classify($item, $duplicates))
            ->values();

        return [
            'has_snapshot' => true,
            'snapshot' => $snapshot,
            'counts' => [
                'total' => $items->count(),
                'products' => $items->where('item_type', 'product')->count(),
                'variable_parents' => $items->where('woo_type', 'variable')->count(),
                'sellable_variations' => $items->where('item_type', 'variation')->count(),
                'ready_sku_gtin' => $items->filter(fn (array $item): bool => $item['blocks'] === [] && $item['sku'] !== null && $item['gtin'] !== null)->count(),
                'ready_sku_only' => $items->filter(fn (array $item): bool => $item['blocks'] === [] && $item['sku'] !== null && $item['gtin'] === null)->count(),
                'needs_attention' => $items->where('status', 'needs_attention')->count(),
                'blocked' => $items->where('status', 'blocked')->count(),
                'missing_sku' => $items->where('missing_sku', true)->count(),
                'missing_price' => $items->where('missing_price', true)->count(),
                'missing_gtin' => $items->where('missing_gtin', true)->count(),
                'duplicate_sku_items' => $items->where('duplicate_sku', true)->count(),
                'duplicate_gtin_items' => $items->where('duplicate_gtin', true)->count(),
            ],
            'duplicates' => $duplicates,
            'fixes' => $this->fixes($items, $duplicates),
            'items' => $items->take(40)->values()->all(),
        ];
    }

    private function emptyCounts(): array
    {
        return [
            'total' => 0,
            'products' => 0,
            'variable_parents' => 0,
            'sellable_variations' => 0,
            'ready_sku_gtin' => 0,
            'ready_sku_only' => 0,
            'needs_attention' => 0,
            'blocked' => 0,
            'missing_sku' => 0,
            'missing_price' => 0,
            'missing_gtin' => 0,
            'duplicate_sku_items' => 0,
            'duplicate_gtin_items' => 0,
        ];
    }

    private function items(ConnectionDiscoverySnapshot $snapshot): Collection
    {
        $products = collect($snapshot->sample_json['products'] ?? [])
            ->filter(fn ($product): bool => is_array($product))
            ->map(fn (array $product): array => $this->normalizeProduct($product));

        $variations = collect($snapshot->sample_json['variations'] ?? [])
            ->filter(fn ($variation): bool => is_array($variation))
            ->filter(fn (array $variation): bool => ($variation['discovery_status'] ?? 'success') === 'success')
            ->map(fn (array $variation): array => $this->normalizeVariation($variation));

        return $products->concat($variations)->values();
    }

    private function normalizeProduct(array $product): array
    {
        return [
            'item_key' => 'product:' . (string) ($product['id'] ?? ''),
            'item_type' => 'product',
            'woo_type' => $product['type'] ?? null,
            'woo_product_id' => $product['id'] ?? null,
            'woo_variation_id' => null,
            'parent_id' => null,
            'name' => $product['name'] ?? null,
            'sku' => $this->stringValue($product['sku'] ?? null),
            'gtin' => $this->stringValue($product['gtin_candidate']['value'] ?? null),
            'gtin_key' => $product['gtin_candidate']['key'] ?? null,
            'price' => $this->price($product),
            'stock_status' => $product['stock_status'] ?? null,
            'categories' => $product['categories'] ?? [],
        ];
    }

    private function normalizeVariation(array $variation): array
    {
        return [
            'item_key' => 'variation:' . (string) ($variation['id'] ?? ''),
            'item_type' => 'variation',
            'woo_type' => 'variation',
            'woo_product_id' => $variation['parent_id'] ?? null,
            'woo_variation_id' => $variation['id'] ?? null,
            'parent_id' => $variation['parent_id'] ?? null,
            'name' => $variation['name'] ?? null,
            'sku' => $this->stringValue($variation['sku'] ?? null),
            'gtin' => $this->stringValue($variation['gtin_candidate']['value'] ?? null),
            'gtin_key' => $variation['gtin_candidate']['key'] ?? null,
            'price' => $this->price($variation),
            'stock_status' => $variation['stock_status'] ?? null,
            'categories' => $variation['categories'] ?? [],
        ];
    }

    private function classify(array $item, array $duplicates): array
    {
        $missingSku = $item['sku'] === null;
        $missingGtin = $item['gtin'] === null;
        $missingPrice = $item['price'] === null;
        $duplicateSku = $item['sku'] !== null && in_array($item['sku'], $duplicates['skus'], true);
        $duplicateGtin = $item['gtin'] !== null && in_array($item['gtin'], $duplicates['gtins'], true);
        $warnings = [];
        $blocks = [];

        if ($missingSku) {
            $blocks[] = 'Missing SKU';
        }

        if ($missingSku && $missingGtin) {
            $blocks[] = 'Missing both SKU and GTIN/EAN';
        } elseif ($missingGtin) {
            $warnings[] = 'Missing GTIN/EAN, SKU fallback available';
        }

        if ($missingPrice) {
            $blocks[] = 'Missing price';
        }

        if ($duplicateSku) {
            $blocks[] = 'Duplicate SKU';
        }

        if ($duplicateGtin) {
            $blocks[] = 'Duplicate GTIN/EAN';
        }

        if ($item['woo_type'] === 'variable') {
            $warnings[] = 'Variable parent; variations are usually the sellable POS items';
        }

        if (($item['stock_status'] ?? null) === 'outofstock') {
            $warnings[] = 'Out of stock';
        }

        $item['missing_sku'] = $missingSku;
        $item['missing_gtin'] = $missingGtin;
        $item['missing_price'] = $missingPrice;
        $item['duplicate_sku'] = $duplicateSku;
        $item['duplicate_gtin'] = $duplicateGtin;
        $item['blocks'] = $blocks;
        $item['warnings'] = $warnings;
        $item['status'] = $blocks !== [] ? 'blocked' : ($warnings !== [] ? 'needs_attention' : 'ready');

        return $item;
    }

    private function fixes(Collection $items, array $duplicates): array
    {
        return [
            [
                'label' => 'Add missing SKUs',
                'count' => $items->where('missing_sku', true)->count(),
                'help' => 'SKU is the main identifier for products without EAN/GTIN.',
            ],
            [
                'label' => 'Add missing prices',
                'count' => $items->where('missing_price', true)->count(),
                'help' => 'Front needs a price candidate before a future product write test.',
            ],
            [
                'label' => 'Review SKU-only products',
                'count' => $items->where('missing_gtin', true)->where('missing_sku', false)->count(),
                'help' => 'These can continue with SKU fallback, but barcode scanning may need EAN/GTIN later.',
            ],
            [
                'label' => 'Resolve duplicate SKUs',
                'count' => count($duplicates['skus']),
                'help' => 'Duplicate SKUs are unsafe for matching and should be fixed before sync.',
            ],
            [
                'label' => 'Resolve duplicate GTIN/EAN values',
                'count' => count($duplicates['gtins']),
                'help' => 'Duplicate barcodes can point Front to the wrong size or product.',
            ],
        ];
    }

    private function duplicates(Collection $items, string $key): array
    {
        return $items
            ->pluck($key)
            ->filter()
            ->countBy()
            ->filter(fn (int $count): bool => $count > 1)
            ->keys()
            ->values()
            ->all();
    }

    private function price(array $item): ?string
    {
        return $this->stringValue($item['regular_price'] ?? null)
            ?? $this->stringValue($item['price'] ?? null);
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
