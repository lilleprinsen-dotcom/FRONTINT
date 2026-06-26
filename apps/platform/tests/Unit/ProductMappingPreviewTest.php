<?php

namespace Tests\Unit;

use App\Services\Discovery\ProductMappingPreview;
use PHPUnit\Framework\TestCase;

class ProductMappingPreviewTest extends TestCase
{
    public function test_mapping_preview_matches_gtin_first(): void
    {
        $preview = new ProductMappingPreview();

        $rows = $preview->preview([
            [
                'id' => 123,
                'name' => 'Woo Boot',
                'sku' => 'SKU-ALSO-MATCHES',
                'gtin_candidate' => ['value' => '7040000000012'],
            ],
        ], [
            [
                'productid' => 501,
                'name' => 'Front GTIN Match',
                'productSizes' => [
                    ['gtin' => '7040000000012', 'identity' => 'OTHER', 'externalSKU' => 'OTHER'],
                ],
            ],
            [
                'productid' => 502,
                'name' => 'Front SKU Match',
                'productSizes' => [
                    ['gtin' => '999', 'identity' => 'SKU-ALSO-MATCHES', 'externalSKU' => 'SKU-ALSO-MATCHES'],
                ],
            ],
        ]);

        $this->assertSame('gtin', $rows[0]['match_method']);
        $this->assertSame('high', $rows[0]['confidence']);
        $this->assertSame('Front GTIN Match', $rows[0]['front_match']['name']);
    }

    public function test_mapping_preview_falls_back_to_external_sku(): void
    {
        $preview = new ProductMappingPreview();

        $rows = $preview->preview([
            [
                'id' => 123,
                'name' => 'Woo Boot',
                'sku' => 'BOOT-24',
                'gtin_candidate' => ['value' => null],
            ],
        ], [
            [
                'productid' => 501,
                'name' => 'Front Boot',
                'productSizes' => [
                    ['gtin' => '7040000000012', 'identity' => 'IDENT-24', 'externalSKU' => 'BOOT-24'],
                ],
            ],
        ]);

        $this->assertSame('sku_external_sku', $rows[0]['match_method']);
        $this->assertSame('medium', $rows[0]['confidence']);
        $this->assertSame('Front Boot', $rows[0]['front_match']['name']);
    }

    public function test_mapping_preview_falls_back_to_identity_after_external_sku(): void
    {
        $preview = new ProductMappingPreview();

        $rows = $preview->preview([
            [
                'id' => 123,
                'name' => 'Woo Boot',
                'sku' => 'IDENT-24',
                'gtin_candidate' => ['value' => null],
            ],
        ], [
            [
                'productid' => 501,
                'name' => 'Front Boot',
                'productSizes' => [
                    ['gtin' => '7040000000012', 'identity' => 'IDENT-24', 'externalSKU' => 'OTHER'],
                ],
            ],
        ]);

        $this->assertSame('sku_identity', $rows[0]['match_method']);
        $this->assertSame('medium', $rows[0]['confidence']);
        $this->assertSame('Front Boot', $rows[0]['front_match']['name']);
    }
}
