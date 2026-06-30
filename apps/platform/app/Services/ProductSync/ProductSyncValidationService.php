<?php

namespace App\Services\ProductSync;

use App\Models\ProductSyncProfile;

class ProductSyncValidationService
{
    public function validateCandidate(
        array $candidate,
        ProductSyncProfile $profile,
        array $duplicateGtins = [],
        array $duplicateSkus = [],
    ): array {
        $woo = $candidate['woo_product'] ?? [];
        $payload = $candidate['proposed_front_payload'] ?? [];
        $size = $payload['productSizes'][0] ?? [];
        $errors = $candidate['blocks'] ?? [];
        $warnings = $candidate['warnings'] ?? [];
        $sku = $this->stringValue($woo['sku'] ?? null);
        $gtin = $this->stringValue($size['gtin'] ?? $candidate['gtin_candidate']['value'] ?? null);
        $type = $this->stringValue($woo['type'] ?? null);

        if ($this->stringValue($woo['name'] ?? null) === null) {
            $errors[] = 'Missing product name.';
        }

        if ($profile->require_sku && $sku === null) {
            $errors[] = 'Missing SKU.';
        }

        if ($profile->require_gtin && $gtin === null) {
            $errors[] = 'Missing GTIN/EAN candidate.';
        } elseif (! $profile->require_gtin && $gtin === null) {
            $warnings[] = 'Missing GTIN/EAN candidate; SKU fallback may be used if the SKU is unique and approved.';
        }

        if ($profile->require_price && $this->stringValue($payload['price_candidate'] ?? null) === null) {
            $errors[] = 'No price candidate exists.';
        }

        if ($profile->require_brand && $this->stringValue($payload['brand'] ?? null) === null) {
            $errors[] = 'Missing brand.';
        }

        if ($profile->require_category && $this->stringValue($payload['groupName'] ?? null) === null) {
            $errors[] = 'Missing category.';
        }

        if ($type === 'simple' && ! $profile->include_simple_products) {
            $errors[] = 'Simple products are disabled in this sync profile.';
        }

        if ($type === 'variable' && ! $profile->include_variable_products) {
            $errors[] = 'Variable products are disabled in this sync profile.';
        }

        if ($type === 'variation') {
            if (! $profile->include_variations) {
                $errors[] = 'Variations are disabled in this sync profile.';
            }

            if (empty($woo['parent_product_id'])) {
                $errors[] = 'Variation is missing parent product context.';
            }
        }

        if ($gtin !== null && in_array($gtin, $duplicateGtins, true)) {
            $errors[] = 'Duplicate GTIN/EAN within sync run.';
        }

        if ($sku !== null && in_array($sku, $duplicateSkus, true)) {
            $errors[] = 'Duplicate SKU within sync run.';
        }

        if ($this->stringValue($payload['brand'] ?? null) === null) {
            $warnings[] = 'Missing brand.';
        }

        if ($this->stringValue($payload['groupName'] ?? null) === null) {
            $warnings[] = 'Missing category.';
        }

        if (($woo['stock_status'] ?? null) === 'outofstock') {
            $warnings[] = 'Out of stock.';
        }

        if (($woo['manage_stock'] ?? null) === false) {
            $warnings[] = 'manage_stock=false.';
        }

        if (($candidate['front_match']['status'] ?? 'no_match') === 'no_match') {
            $warnings[] = 'No Front match found.';
        }

        if (($candidate['gtin_candidate']['confidence'] ?? null) !== 'exact_known_field') {
            $warnings[] = 'Uncertain GTIN/EAN field.';
        }

        if ($this->stringValue($payload['sale_price_candidate'] ?? null) !== null) {
            $warnings[] = 'Sale price is not included in product sync yet.';
        }

        if (in_array($profile->stock_strategy, ['do_not_sync_stock_yet', 'preview_only'], true)) {
            $warnings[] = 'Stock is not included in this phase.';
        }

        $errors = array_values(array_unique($errors));
        $warnings = array_values(array_unique($warnings));

        return [
            'status' => $errors !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'ready'),
            'errors' => $errors,
            'warnings' => $warnings,
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
