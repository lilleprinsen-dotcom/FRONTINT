<?php

namespace Tests\Unit;

use App\Support\IdempotencyKey;
use Tests\TestCase;

class IdempotencyKeyTest extends TestCase
{
    public function test_it_builds_a_stable_key_from_parts(): void
    {
        $key = IdempotencyKey::build('Lilleprinsen', 'WooCommerce', 'Order Created', 'ABC:123');

        $this->assertSame('lilleprinsen:woocommerce:order created:abc-123', $key);
    }

    public function test_payload_hash_is_stable_for_key_order(): void
    {
        $first = IdempotencyKey::payloadHash(['b' => 2, 'a' => 1]);
        $second = IdempotencyKey::payloadHash(['a' => 1, 'b' => 2]);

        $this->assertSame($first, $second);
    }

    public function test_payload_hash_changes_when_payload_changes(): void
    {
        $first = IdempotencyKey::payloadHash(['order_id' => 1001]);
        $second = IdempotencyKey::payloadHash(['order_id' => 1002]);

        $this->assertNotSame($first, $second);
    }
}
