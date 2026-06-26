<?php

namespace App\Services\Discovery;

class ProductMappingPreview
{
    public function preview(array $wooProducts, array $frontProducts): array
    {
        $frontSizes = $this->flattenFrontSizes($frontProducts);

        return collect($wooProducts)
            ->take(10)
            ->map(fn (array $wooProduct): array => $this->matchWooProduct($wooProduct, $frontSizes))
            ->values()
            ->all();
    }

    private function matchWooProduct(array $wooProduct, array $frontSizes): array
    {
        $wooGtin = $wooProduct['gtin_candidate']['value'] ?? null;
        $wooSku = $wooProduct['sku'] ?? null;
        $match = null;
        $method = 'none';
        $confidence = 'none';

        if (is_string($wooGtin) && $wooGtin !== '') {
            $match = collect($frontSizes)->first(fn (array $frontSize): bool => (string) ($frontSize['gtin'] ?? '') === $wooGtin);
            $method = $match ? 'gtin' : $method;
            $confidence = $match ? 'high' : $confidence;
        }

        if (! $match && is_string($wooSku) && $wooSku !== '') {
            $match = collect($frontSizes)->first(fn (array $frontSize): bool => (string) ($frontSize['external_sku'] ?? '') === $wooSku);
            $method = $match ? 'sku_external_sku' : $method;
            $confidence = $match ? 'medium' : $confidence;
        }

        if (! $match && is_string($wooSku) && $wooSku !== '') {
            $match = collect($frontSizes)->first(fn (array $frontSize): bool => (string) ($frontSize['identity'] ?? '') === $wooSku);
            $method = $match ? 'sku_identity' : $method;
            $confidence = $match ? 'medium' : $confidence;
        }

        return [
            'woo_product' => [
                'id' => $wooProduct['id'] ?? null,
                'name' => $wooProduct['name'] ?? null,
            ],
            'woo_gtin' => $wooGtin,
            'woo_sku' => $wooSku,
            'front_match' => $match,
            'match_method' => $method,
            'confidence' => $confidence,
            'warning' => $match ? null : 'No preview match found.',
        ];
    }

    private function flattenFrontSizes(array $frontProducts): array
    {
        return collect($frontProducts)
            ->flatMap(function (array $product): array {
                $sizes = $product['productSizes'] ?? [];

                if (! is_array($sizes)) {
                    return [];
                }

                return collect($sizes)
                    ->filter(fn ($size): bool => is_array($size))
                    ->map(fn (array $size): array => [
                        'productid' => $product['productid'] ?? null,
                        'name' => $product['name'] ?? null,
                        'brand' => $product['brand'] ?? null,
                        'groupName' => $product['groupName'] ?? null,
                        'subgroupName' => $product['subgroupName'] ?? null,
                        'label' => $size['label'] ?? null,
                        'gtin' => $size['gtin'] ?? null,
                        'identity' => $size['identity'] ?? null,
                        'external_sku' => $size['externalSKU'] ?? null,
                    ])
                    ->all();
            })
            ->values()
            ->all();
    }
}
