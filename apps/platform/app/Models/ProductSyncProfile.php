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
        'woo_query_limit',
        'front_write_limit',
        'sync_only_opted_in_products',
        'include_simple_products',
        'include_variable_products',
        'include_variations',
        'require_sku',
        'require_gtin',
        'require_price',
        'require_brand',
        'require_category',
        'default_front_group_strategy',
        'default_front_subgroup_strategy',
        'default_front_brand_strategy',
        'price_strategy',
        'stock_strategy',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sync_only_opted_in_products' => 'boolean',
            'include_simple_products' => 'boolean',
            'include_variable_products' => 'boolean',
            'include_variations' => 'boolean',
            'require_sku' => 'boolean',
            'require_gtin' => 'boolean',
            'require_price' => 'boolean',
            'require_brand' => 'boolean',
            'require_category' => 'boolean',
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
