<?php

namespace App\Services\ProductSync;

use App\Models\ConnectionDiscoverySnapshot;
use App\Models\ProductSyncProfile;
use App\Models\ProductSyncRun;
use App\Models\ProductSyncRunItem;
use App\Models\User;
use App\Services\Mapping\ProductSyncPreviewPlanner;
use Illuminate\Support\Facades\DB;

class StagingBatchProductSyncRunBuilder
{
    public const MAX_ITEMS = 100;

    public function __construct(
        private readonly ProductSyncPreviewPlanner $planner,
        private readonly ProductSyncValidationService $validator,
    ) {
    }

    /**
     * @param array<int, string> $wooItemKeys
     */
    public function createFromWooDiscovery(
        User $user,
        ProductSyncProfile $profile,
        ConnectionDiscoverySnapshot $wooSnapshot,
        array $wooItemKeys,
    ): ProductSyncRun {
        $selectedKeys = collect($wooItemKeys)
            ->map(fn (mixed $key): string => trim((string) $key))
            ->filter()
            ->unique()
            ->take(self::MAX_ITEMS)
            ->values();
        $allItems = $this->planner->wooItemsFromSnapshot($wooSnapshot);
        $allPreviewRows = collect($this->planner->previewRows($allItems->all(), []));
        $previewRows = $this->enrichVariableParents(
            $allPreviewRows
                ->filter(fn (array $row): bool => $selectedKeys->contains($row['woo_product']['item_key'] ?? $this->planner->wooItemKey($row['woo_product'] ?? [])))
                ->values(),
            $allPreviewRows,
        );
        $duplicateGtins = $this->duplicateValues($previewRows, fn (array $row): ?string => $this->stringValue($row['proposed_front_payload']['productSizes'][0]['gtin'] ?? null));
        $duplicateSkus = $this->duplicateValues($previewRows, fn (array $row): ?string => $this->stringValue($row['woo_product']['sku'] ?? null));

        return DB::transaction(function () use ($user, $profile, $wooSnapshot, $previewRows, $duplicateGtins, $duplicateSkus): ProductSyncRun {
            $runItems = collect($previewRows)
                ->map(fn (array $row): array => $this->runItemAttributes($row, $profile, $duplicateGtins, $duplicateSkus))
                ->values();
            $ready = $runItems->whereIn('validation_status', ['ready', 'warning'])->count();
            $blocked = $runItems->where('validation_status', 'blocked')->count();
            $variations = $runItems->filter(fn (array $item): bool => $item['woo_variation_id'] !== null)->count();

            $run = ProductSyncRun::query()->create([
                'organization_id' => $profile->organization_id,
                'product_sync_profile_id' => $profile->id,
                'created_by_user_id' => $user->id,
                'run_type' => 'staging_batch',
                'status' => 'draft',
                'mode' => $profile->mode,
                'scope' => 'selected_only',
                'cursor_json' => [
                    'source' => 'connection_discovery_snapshots',
                    'woo_snapshot_id' => $wooSnapshot->id,
                ],
                'checkpoint_json' => [
                    'processed_items' => 0,
                    'next_action' => 'run_staging_batch',
                ],
                'total_candidates' => $runItems->count(),
                'total_ready' => $ready,
                'total_blocked' => $blocked,
                'total_synced' => 0,
                'total_failed' => 0,
                'total_skipped' => 0,
                'total_pending' => $runItems->count(),
                'total_variations' => $variations,
                'summary_json' => [
                    'source_woo_snapshot_id' => $wooSnapshot->id,
                    'staging_batch' => true,
                    'max_items' => self::MAX_ITEMS,
                    'external_api_calls' => false,
                    'writes_performed' => false,
                    'no_woo_writes' => true,
                    'no_price_list_writes' => true,
                    'no_stock_writes' => true,
                ],
            ]);

            $runItems->each(function (array $attributes) use ($run): void {
                ProductSyncRunItem::query()->create($attributes + [
                    'organization_id' => $run->organization_id,
                    'product_sync_run_id' => $run->id,
                ]);
            });

            return $run->fresh(['items', 'profile']);
        });
    }

    private function runItemAttributes(
        array $row,
        ProductSyncProfile $profile,
        array $duplicateGtins,
        array $duplicateSkus,
    ): array {
        $wooProduct = $row['woo_product'] ?? [];
        $payload = $row['proposed_front_payload'] ?? [];
        $size = $payload['productSizes'][0] ?? [];
        $validation = $this->validator->validateCandidate($row, $profile, $duplicateGtins, $duplicateSkus);
        $wooProductId = (int) ($wooProduct['parent_product_id'] ?? $wooProduct['id'] ?? 0);
        $wooVariationId = ($wooProduct['type'] ?? null) === 'variation' ? (int) ($wooProduct['id'] ?? 0) : null;
        $wooItemKey = $wooVariationId ? "variation:{$wooVariationId}" : 'product:' . ($wooProduct['id'] ?? 'unknown');

        return [
            'woo_product_id' => $wooProductId,
            'woo_variation_id' => $wooVariationId,
            'woo_item_key' => $wooItemKey,
            'woo_parent_product_id' => $wooProduct['parent_product_id'] ?? null,
            'woo_name' => $wooProduct['name'] ?? null,
            'woo_type' => $wooProduct['type'] ?? null,
            'woo_sku' => $wooProduct['sku'] ?? null,
            'woo_stock_quantity' => is_numeric($wooProduct['stock_quantity'] ?? null) ? (int) $wooProduct['stock_quantity'] : null,
            'detected_gtin' => $size['gtin'] ?? null,
            'detected_gtin_key' => $row['gtin_candidate']['key'] ?? null,
            'front_match_status' => $row['front_match']['status'] ?? null,
            'front_product_id' => $row['front_match']['productid'] ?? null,
            'front_product_ext_id' => null,
            'front_identity' => $payload['number'] ?? null,
            'front_external_sku' => $size['externalSKU'] ?? null,
            'proposed_front_product_ext_id' => null,
            'proposed_front_identity' => $payload['number'] ?? null,
            'proposed_front_external_sku' => $size['externalSKU'] ?? null,
            'proposed_front_payload_json' => $payload,
            'payload_hash' => hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            'validation_status' => $validation['status'],
            'sync_status' => 'not_started',
            'validation_errors_json' => $validation['errors'],
            'validation_warnings_json' => $validation['warnings'],
            'attempt_count' => 0,
        ];
    }

    private function enrichVariableParents($selectedRows, $allRows)
    {
        return $selectedRows
            ->map(function (array $row) use ($allRows): array {
                $wooProduct = $row['woo_product'] ?? [];

                if (($wooProduct['type'] ?? null) !== 'variable') {
                    return $row;
                }

                $parentId = $wooProduct['id'] ?? null;
                $variationRows = $allRows
                    ->filter(fn (array $candidate): bool => ($candidate['woo_product']['type'] ?? null) === 'variation'
                        && (string) ($candidate['woo_product']['parent_product_id'] ?? '') === (string) $parentId)
                    ->values();

                if ($variationRows->isEmpty()) {
                    return $row;
                }

                $sizes = $variationRows
                    ->map(fn (array $candidate): array => $candidate['proposed_front_payload']['productSizes'][0] ?? [])
                    ->filter(fn (array $size): bool => array_filter($size) !== [])
                    ->values()
                    ->all();

                if ($sizes === []) {
                    return $row;
                }

                $row['proposed_front_payload']['productSizes'] = $sizes;
                $row['proposed_front_payload']['variant'] = $wooProduct['sku'] ?? null;
                $row['proposed_front_payload']['variable_parent_with_variations'] = true;
                $row['proposed_front_payload']['variation_count'] = count($sizes);
                $row['warnings'][] = 'Variable parent will be written as one Front product with discovered Woo variations as product sizes.';
                $row['blocks'] = array_values(array_filter(
                    $row['blocks'] ?? [],
                    fn (string $block): bool => $block !== 'Missing GTIN/EAN candidate.'
                ));

                return $row;
            })
            ->values();
    }

    private function duplicateValues($rows, callable $resolver): array
    {
        return collect($rows)
            ->map($resolver)
            ->filter(fn (?string $value): bool => $value !== null)
            ->countBy()
            ->filter(fn (int $count): bool => $count > 1)
            ->keys()
            ->map(fn (mixed $value): string => (string) $value)
            ->values()
            ->all();
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
