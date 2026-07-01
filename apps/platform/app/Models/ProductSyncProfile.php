<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductSyncProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'is_active',
        'mode',
        'max_products_per_batch',
        'max_products_per_run',
        'woo_page_size',
        'front_page_size',
        'max_runtime_seconds',
        'rate_limit_per_minute',
        'sync_scope',
        'woo_query_limit',
        'front_write_limit',
        'sync_only_opted_in_products',
        'include_simple_products',
        'include_variable_products',
        'include_variations',
        'include_draft_products',
        'include_private_products',
        'include_out_of_stock_products',
        'exclude_discontinued_products',
        'require_sku',
        'require_gtin',
        'require_price',
        'require_brand',
        'require_category',
        'product_identity_strategy',
        'gtin_field_strategy',
        'configured_gtin_meta_key',
        'category_mapping_strategy',
        'brand_mapping_strategy',
        'default_front_group_strategy',
        'default_front_subgroup_strategy',
        'default_front_brand_strategy',
        'price_strategy',
        'sale_price_list_name',
        'stock_strategy',
        'incremental_sync_enabled',
        'webhook_updates_enabled',
        'reconciliation_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sync_only_opted_in_products' => 'boolean',
            'include_simple_products' => 'boolean',
            'include_variable_products' => 'boolean',
            'include_variations' => 'boolean',
            'include_draft_products' => 'boolean',
            'include_private_products' => 'boolean',
            'include_out_of_stock_products' => 'boolean',
            'exclude_discontinued_products' => 'boolean',
            'require_sku' => 'boolean',
            'require_gtin' => 'boolean',
            'require_price' => 'boolean',
            'require_brand' => 'boolean',
            'require_category' => 'boolean',
            'incremental_sync_enabled' => 'boolean',
            'webhook_updates_enabled' => 'boolean',
            'reconciliation_enabled' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ProductSyncRun::class);
    }
}
