<?php

namespace App\Services\ProductSync;

use App\Models\ProductSyncPreviewPlan;
use App\Models\ProductSyncProfile;
use App\Models\ProductSyncRun;
use App\Models\ProductSyncRunItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProductSyncPreviewRunBuilder
{
    public function __construct(private readonly ProductSyncValidationService $validator)
    {
    }

    public function createFromPreviewPlan(
        User $user,
        ProductSyncProfile $profile,
        ProductSyncPreviewPlan $plan,
    ): ProductSyncRun {
        $rows = collect($plan->plan_json['rows'] ?? [])
            ->filter(fn ($row): bool => is_array($row))
            ->take($profile->max_products_per_run)
            ->values();

        return DB::transaction(function () use ($user, $profile, $plan, $rows): ProductSyncRun {
            $duplicateGtins = $this->duplicateValues($rows, fn (array $row): ?string => $this->gtinValue($row));
            $duplicateSkus = $this->duplicateValues($rows, fn (array $row): ?string => $this->stringValue($row['woo_product']['sku'] ?? null));
            $items = $rows
                ->map(fn (array $row): array => $this->runItemAttributes($row, $profile, $duplicateGtins, $duplicateSkus))
                ->values();
            $ready = $items->where('validation_status', 'ready')->count();
            $warning = $items->where('validation_status', 'warning')->count();
            $blocked = $items->where('validation_status', 'blocked')->count();
            $variations = $items->filter(fn (array $item): bool => $item['woo_variation_id'] !== null || $item['woo_type'] === 'variation')->count();

            $run = ProductSyncRun::query()->create([
                'organization_id' => $profile->organization_id,
                'product_sync_profile_id' => $profile->id,
                'created_by_user_id' => $user->id,
                'run_type' => 'preview',
                'status' => 'draft',
                'mode' => $profile->mode,
                'scope' => $profile->sync_scope,
                'cursor_json' => [
                    'source' => 'product_sync_preview_plans',
                    'preview_plan_id' => $plan->id,
                ],
                'checkpoint_json' => [
                    'processed_items' => 0,
                    'next_action' => 'manual_review',
                ],
                'total_candidates' => $items->count(),
                'total_ready' => $ready + $warning,
                'total_blocked' => $blocked,
                'total_synced' => 0,
                'total_failed' => 0,
                'total_skipped' => 0,
                'total_pending' => $items->count(),
                'total_variations' => $variations,
                'summary_json' => [
                    'source_preview_plan_id' => $plan->id,
                    'preview_only' => true,
                    'external_api_calls' => false,
                    'writes_performed' => false,
                    'max_products_per_run' => $profile->max_products_per_run,
                    'truncated_to_profile_limit' => $rows->count() < count($plan->plan_json['rows'] ?? []),
                    'large_catalog_note' => 'Sync runs store selected items only. Full catalog scanning must run as a future background job.',
                ],
            ]);

            $items->each(function (array $attributes) use ($run): void {
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
    ): array
    {
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

    private function gtinValue(array $row): ?string
    {
        return $this->stringValue($row['proposed_front_payload']['productSizes'][0]['gtin'] ?? $row['gtin_candidate']['value'] ?? null);
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
