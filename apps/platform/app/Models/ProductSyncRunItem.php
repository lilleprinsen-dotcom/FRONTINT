<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSyncRunItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'product_sync_run_id',
        'woo_product_id',
        'woo_variation_id',
        'woo_item_key',
        'woo_parent_product_id',
        'woo_name',
        'woo_type',
        'woo_sku',
        'detected_gtin',
        'detected_gtin_key',
        'front_match_status',
        'front_product_id',
        'front_product_ext_id',
        'front_identity',
        'front_external_sku',
        'proposed_front_product_ext_id',
        'proposed_front_identity',
        'proposed_front_external_sku',
        'proposed_front_payload_json',
        'payload_hash',
        'validation_status',
        'sync_status',
        'validation_errors_json',
        'validation_warnings_json',
        'last_error',
        'attempt_count',
        'last_attempted_at',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'proposed_front_payload_json' => 'array',
            'validation_errors_json' => 'array',
            'validation_warnings_json' => 'array',
            'last_attempted_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ProductSyncRun::class, 'product_sync_run_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
