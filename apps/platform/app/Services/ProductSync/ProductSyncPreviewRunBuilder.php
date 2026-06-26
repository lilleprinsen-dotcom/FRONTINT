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
            $items = $rows->map(fn (array $row): array => $this->runItemAttributes($row, $profile))->values();
            $ready = $items->where('validation_status', 'ready')->count();
            $blocked = $items->where('validation_status', 'blocked')->count();
            $warning = $items->where('validation_status', 'warning')->count();

            $run = ProductSyncRun::query()->create([
                'organization_id' => $profile->organization_id,
                'product_sync_profile_id' => $profile->id,
                'created_by_user_id' => $user->id,
                'status' => 'draft',
                'mode' => $profile->mode,
                'total_candidates' => $items->count(),
                'total_ready' => $ready + $warning,
                'total_blocked' => $blocked,
                'total_synced' => 0,
                'total_failed' => 0,
                'total_skipped' => 0,
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

    private function runItemAttributes(array $row, ProductSyncProfile $profile): array
    {
        $wooProduct = $row['woo_product'] ?? [];
        $payload = $row['proposed_front_payload'] ?? [];
        $size = $payload['productSizes'][0] ?? [];
        $errors = $row['blocks'] ?? [];
        $warnings = $row['warnings'] ?? [];

        if ($profile->require_sku && empty($wooProduct['sku'])) {
            $errors[] = 'Missing SKU.';
        }

        if ($profile->require_gtin && empty($size['gtin'])) {
            $errors[] = 'Missing GTIN/EAN candidate.';
        }

        if ($profile->require_price && empty($payload['price_candidate'])) {
            $errors[] = 'No price candidate exists.';
        }

        if ($profile->require_brand && empty($payload['brand'])) {
            $errors[] = 'Missing brand.';
        }

        if ($profile->require_category && empty($payload['groupName'])) {
            $errors[] = 'Missing category.';
        }

        if (! $profile->include_variable_products && ($wooProduct['type'] ?? null) === 'variable') {
            $errors[] = 'Variable products are disabled in this sync profile.';
        }

        $errors = array_values(array_unique($errors));
        $warnings = array_values(array_unique($warnings));
        $validationStatus = $errors !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'ready');

        return [
            'woo_product_id' => (int) ($wooProduct['id'] ?? 0),
            'woo_variation_id' => null,
            'woo_item_key' => 'product:' . ($wooProduct['id'] ?? 'unknown'),
            'woo_name' => $wooProduct['name'] ?? null,
            'woo_sku' => $wooProduct['sku'] ?? null,
            'detected_gtin' => $size['gtin'] ?? null,
            'detected_gtin_key' => $row['gtin_candidate']['key'] ?? null,
            'front_match_status' => $row['front_match']['status'] ?? null,
            'proposed_front_product_ext_id' => null,
            'proposed_front_identity' => $payload['number'] ?? null,
            'proposed_front_external_sku' => $size['externalSKU'] ?? null,
            'proposed_front_payload_json' => $payload,
            'validation_status' => $validationStatus,
            'sync_status' => 'not_started',
            'validation_errors_json' => $errors,
            'validation_warnings_json' => $warnings,
        ];
    }
}
