<?php

namespace Tests\Unit;

use App\Services\Discovery\WooCommerceGtinCandidateDetector;
use PHPUnit\Framework\TestCase;

class WooCommerceGtinCandidateDetectorTest extends TestCase
{
    public function test_known_lilleprinsen_fields_are_detected_first(): void
    {
        $detector = new WooCommerceGtinCandidateDetector();

        $result = $detector->detect([
            'meta_data' => [
                ['key' => 'ean', 'value' => '111'],
                ['key' => 'Zettle_barcode', 'value' => '7040000000012'],
            ],
        ]);

        $this->assertSame('Zettle_barcode', $result['key']);
        $this->assertSame('7040000000012', $result['value']);
        $this->assertSame('exact_known_field', $result['confidence']);
    }

    public function test_multiple_candidates_are_returned_for_confirmation(): void
    {
        $detector = new WooCommerceGtinCandidateDetector();

        $result = $detector->detect([
            'meta_data' => [
                ['key' => 'Zettle_barcode', 'value' => '7040000000012'],
                ['key' => '_gtin', 'value' => '7040000000098'],
            ],
        ]);

        $this->assertSame('Zettle_barcode', $result['key']);
        $this->assertCount(2, $result['candidates']);
        $this->assertSame('_gtin', $result['candidates'][1]['key']);
    }

    public function test_common_fields_are_detected_when_known_fields_are_missing(): void
    {
        $detector = new WooCommerceGtinCandidateDetector();

        $result = $detector->detect([
            'meta_data' => [
                ['key' => '_gtin', 'value' => '7040000000098'],
            ],
        ]);

        $this->assertSame('_gtin', $result['key']);
        $this->assertSame('7040000000098', $result['value']);
        $this->assertSame('common_field', $result['confidence']);
    }
}
