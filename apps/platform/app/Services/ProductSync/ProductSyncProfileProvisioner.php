<?php

namespace App\Services\ProductSync;

use App\Models\Organization;
use App\Models\ProductSyncProfile;

class ProductSyncProfileProvisioner
{
    public function ensureDefault(Organization $organization): ProductSyncProfile
    {
        $profile = ProductSyncProfile::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'name' => 'Default safe product sync profile',
            ],
            [
                'is_active' => true,
                'mode' => 'preview_only',
                'max_products_per_batch' => 25,
                'max_products_per_run' => 100,
                'woo_page_size' => 50,
                'front_page_size' => 50,
                'max_runtime_seconds' => null,
                'rate_limit_per_minute' => null,
                'sync_scope' => 'selected_only',
                'woo_query_limit' => 100,
                'front_write_limit' => 25,
                'sync_only_opted_in_products' => true,
                'include_simple_products' => true,
                'include_variable_products' => true,
                'include_variations' => true,
                'include_draft_products' => false,
                'include_private_products' => false,
                'include_out_of_stock_products' => true,
                'exclude_discontinued_products' => true,
                'require_sku' => true,
                'require_gtin' => false,
                'require_price' => true,
                'require_brand' => false,
                'require_category' => false,
                'product_identity_strategy' => 'woo_id_as_front_extid',
                'gtin_field_strategy' => 'auto_detect',
                'configured_gtin_meta_key' => null,
                'category_mapping_strategy' => null,
                'brand_mapping_strategy' => null,
                'default_front_group_strategy' => null,
                'default_front_subgroup_strategy' => null,
                'default_front_brand_strategy' => null,
                'price_strategy' => 'regular_price_only',
                'sale_price_list_name' => 'WooCommerce Sale Prices',
                'stock_strategy' => 'do_not_sync_stock_yet',
                'incremental_sync_enabled' => false,
                'webhook_updates_enabled' => false,
                'reconciliation_enabled' => false,
            ],
        );

        if (
            ! $profile->wasRecentlyCreated
            && $profile->mode === 'preview_only'
            && $profile->sync_scope === 'selected_only'
            && $profile->require_gtin
            && $profile->created_at?->equalTo($profile->updated_at)
            && $profile->created_at?->lt(now()->subMinute())
        ) {
            $profile->forceFill(['require_gtin' => false])->save();
        }

        return $profile;
    }
}
