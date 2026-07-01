<?php

namespace App\Services\ProductSync\DryRun;

use App\Models\Connection;
use App\Models\ProductSyncRun;
use App\Models\ProductSyncRunItem;
use Illuminate\Support\Collection;

class FrontProductWriteDryRunBuilder
{
    public const MAX_ITEMS = 10;

    public function build(ProductSyncRun $run, array $itemIds): array
    {
        $run->loadMissing(['profile', 'organization.connections', 'items']);

        $selectedIds = collect($itemIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $items = $run->items()
            ->whereIn('id', $selectedIds)
            ->orderBy('id')
            ->get();

        $frontConnection = $this->frontConnection($run);
        $gateErrors = $this->gateErrors($run, $selectedIds, $items, $frontConnection);

        return [
            'status' => $gateErrors === [] ? 'ready' : 'blocked',
            'gate_errors' => $gateErrors,
            'summary' => [
                'selected_count' => $items->count(),
                'max_items' => self::MAX_ITEMS,
                'profile_mode' => $run->profile?->mode,
                'production_writes_enabled' => (bool) config('omnibridge.allow_production_writes'),
                'front_connection_id' => $frontConnection?->id,
                'front_connection_name' => $frontConnection?->name,
                'external_api_calls' => false,
                'writes_performed' => false,
            ],
            'rows' => $items
                ->map(fn (ProductSyncRunItem $item): array => $this->row($item))
                ->values()
                ->all(),
        ];
    }

    public function eligibleItems(ProductSyncRun $run): Collection
    {
        return $run->items()
            ->whereIn('validation_status', ['ready', 'warning'])
            ->where('sync_status', 'not_started')
            ->orderBy('id')
            ->limit(self::MAX_ITEMS)
            ->get();
    }

    private function gateErrors(ProductSyncRun $run, Collection $selectedIds, Collection $items, ?Connection $frontConnection): array
    {
        $errors = [];

        if ((bool) config('omnibridge.allow_production_writes')) {
            $errors[] = 'Production writes must remain disabled for this dry-run milestone.';
        }

        if (($run->profile?->mode) !== 'limited_write_test') {
            $errors[] = 'Product sync profile mode must be limited_write_test.';
        }

        if (! $frontConnection) {
            $errors[] = 'A Front Systems connection must exist before preparing a Front write dry-run.';
        }

        if ($selectedIds->isEmpty()) {
            $errors[] = 'Select at least one product or variation.';
        }

        if ($selectedIds->count() > self::MAX_ITEMS) {
            $errors[] = 'Select no more than ' . self::MAX_ITEMS . ' items.';
        }

        if ($items->count() !== $selectedIds->count()) {
            $errors[] = 'One or more selected items were not found in this sync run.';
        }

        $blocked = $items->filter(fn (ProductSyncRunItem $item): bool => $item->validation_status === 'blocked');

        if ($blocked->isNotEmpty()) {
            $errors[] = 'Blocked items cannot be included in a Front write dry-run.';
        }

        $notEligible = $items->filter(fn (ProductSyncRunItem $item): bool => ! in_array($item->validation_status, ['ready', 'warning'], true));

        if ($notEligible->isNotEmpty()) {
            $errors[] = 'Only ready or warning items can be included.';
        }

        return array_values(array_unique($errors));
    }

    private function frontConnection(ProductSyncRun $run): ?Connection
    {
        return $run->organization
            ?->connections
            ->first(fn (Connection $connection): bool => in_array($connection->type, ['front_systems', 'front'], true));
    }

    private function row(ProductSyncRunItem $item): array
    {
        $payload = $item->proposed_front_payload_json ?? [];
        $size = $payload['productSizes'][0] ?? [];

        return [
            'item_id' => $item->id,
            'woo_item_key' => $item->woo_item_key,
            'woo_name' => $item->woo_name,
            'validation_status' => $item->validation_status,
            'warnings' => $item->validation_warnings_json ?? [],
            'front_match_status' => $item->front_match_status,
            'write_decision' => $item->front_product_id ? 'update_existing_front_product' : 'create_front_product_candidate',
            'future_endpoint_note' => $item->front_product_id
                ? 'Future write would update the matched Front product after endpoint semantics are confirmed.'
                : 'Future write would create or upsert a Front product after endpoint semantics are confirmed.',
            'front_product_payload' => [
                'name' => $payload['name'] ?? $item->woo_name,
                'number' => $payload['number'] ?? $item->woo_sku,
                'variant' => $payload['variant'] ?? null,
                'brand' => $payload['brand'] ?? null,
                'groupName' => $payload['groupName'] ?? null,
                'subgroupName' => $payload['subgroupName'] ?? null,
                'description' => $payload['description'] ?? null,
                'internalDescription' => $payload['internalDescription'] ?? null,
                'tags' => $payload['tags'] ?? null,
                'image_candidate' => $payload['image_candidate'] ?? null,
                'image_candidates' => $payload['image_candidates'] ?? [],
                'productSizes' => [
                    [
                        'label' => $size['label'] ?? null,
                        'gtin' => $size['gtin'] ?? $item->detected_gtin,
                        'externalSKU' => $size['externalSKU'] ?? $item->woo_sku,
                    ],
                ],
            ],
            'price_candidates' => [
                'regular_price' => $payload['price_candidate'] ?? null,
                'sale_price' => $payload['sale_price_candidate'] ?? null,
                'sale_price_destination' => 'future PriceListV2 candidate; not included in this product write dry-run',
            ],
        ];
    }
}
