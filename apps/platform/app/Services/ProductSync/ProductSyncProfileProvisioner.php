<?php

namespace App\Services\ProductSync;

use App\Models\Organization;
use App\Models\ProductSyncProfile;

class ProductSyncProfileProvisioner
{
    public function ensureDefault(Organization $organization): ProductSyncProfile
    {
        return ProductSyncProfile::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'name' => 'Default safe product sync profile',
            ],
            [
                'is_active' => true,
                'mode' => 'preview_only',
                'max_products_per_batch' => 25,
                'max_products_per_run' => 100,
                'woo_query_limit' => 100,
                'front_write_limit' => 25,
                'sync_only_opted_in_products' => true,
                'include_simple_products' => true,
                'include_variable_products' => false,
                'include_variations' => false,
                'require_sku' => true,
                'require_gtin' => true,
                'require_price' => true,
                'require_brand' => false,
                'require_category' => false,
                'default_front_group_strategy' => null,
                'default_front_subgroup_strategy' => null,
                'default_front_brand_strategy' => null,
                'price_strategy' => 'regular_price_only',
                'stock_strategy' => 'do_not_sync_stock_yet',
            ],
        );
    }
}
