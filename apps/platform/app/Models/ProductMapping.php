<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'woo_product_id',
        'woo_variation_id',
        'front_product_id',
        'sku',
        'ean',
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
