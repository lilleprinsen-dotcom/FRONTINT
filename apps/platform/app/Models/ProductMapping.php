<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'woo_item_key',
        'woo_product_id',
        'woo_variation_id',
        'front_product_id',
        'front_product_ext_id',
        'front_identity',
        'sku',
        'gtin',
        'external_sku',
        'front_stock_id',
        'sync_status',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
    }
}
