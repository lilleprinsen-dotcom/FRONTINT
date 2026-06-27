<?php

namespace App\Services\ProductSync;

use App\Models\Organization;
use App\Models\ProductSyncEvent;

class ProductSyncEventRecorder
{
    public function recordWooChange(
        Organization $organization,
        string $eventType,
        ?int $wooProductId,
        ?int $wooVariationId = null,
        array $summary = [],
        int $priority = 0,
    ): ProductSyncEvent {
        $wooItemKey = $wooVariationId ? "variation:{$wooVariationId}" : ($wooProductId ? "product:{$wooProductId}" : null);
        $dedupeKey = implode(':', array_filter([
            'woocommerce',
            $eventType,
            $wooItemKey,
        ]));

        return ProductSyncEvent::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'dedupe_key' => $dedupeKey,
            ],
            [
                'source_system' => 'woocommerce',
                'event_type' => $eventType,
                'woo_product_id' => $wooProductId,
                'woo_variation_id' => $wooVariationId,
                'woo_item_key' => $wooItemKey,
                'status' => 'pending',
                'priority' => $priority,
                'payload_summary_json' => $summary,
                'received_at' => now(),
            ],
        );
    }
}
