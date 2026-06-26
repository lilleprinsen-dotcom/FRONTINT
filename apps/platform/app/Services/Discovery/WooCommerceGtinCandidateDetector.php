<?php

namespace App\Services\Discovery;

class WooCommerceGtinCandidateDetector
{
    private const KNOWN_KEYS = [
        'zettle_barcode',
        'izettle_barcode',
        '_zettle_barcode',
        '_izettle_barcode',
    ];

    private const COMMON_KEYS = [
        'ean',
        '_ean',
        'gtin',
        '_gtin',
        'barcode',
        '_barcode',
    ];

    public function detect(array $product): array
    {
        $metadata = $product['meta_data'] ?? [];

        if (! is_array($metadata)) {
            return $this->none();
        }

        $normalized = [];
        $candidates = [];

        foreach ($metadata as $item) {
            if (! is_array($item)) {
                continue;
            }

            $key = $item['key'] ?? null;
            $value = $item['value'] ?? null;

            if (! is_string($key) || $key === '' || ! is_scalar($value)) {
                continue;
            }

            $normalized[strtolower($key)] = [
                'key' => $key,
                'value' => trim((string) $value),
            ];
        }

        foreach (self::KNOWN_KEYS as $key) {
            if (($normalized[$key]['value'] ?? '') !== '') {
                $candidates[] = [
                    'key' => $normalized[$key]['key'],
                    'value' => $normalized[$key]['value'],
                    'confidence' => 'exact_known_field',
                ];
            }
        }

        foreach (self::COMMON_KEYS as $key) {
            if (($normalized[$key]['value'] ?? '') !== '') {
                $candidates[] = [
                    'key' => $normalized[$key]['key'],
                    'value' => $normalized[$key]['value'],
                    'confidence' => 'common_field',
                ];
            }
        }

        foreach ($candidates as $candidate) {
            if ($candidate['confidence'] === 'exact_known_field') {
                return [
                    'key' => $candidate['key'],
                    'value' => $candidate['value'],
                    'confidence' => 'exact_known_field',
                    'candidates' => $candidates,
                ];
            }
        }

        foreach ($candidates as $candidate) {
            if ($candidate['confidence'] === 'common_field') {
                return [
                    'key' => $candidate['key'],
                    'value' => $candidate['value'],
                    'confidence' => 'common_field',
                    'candidates' => $candidates,
                ];
            }
        }

        return $this->none();
    }

    private function none(): array
    {
        return [
            'key' => null,
            'value' => null,
            'confidence' => 'none',
            'candidates' => [],
        ];
    }
}
