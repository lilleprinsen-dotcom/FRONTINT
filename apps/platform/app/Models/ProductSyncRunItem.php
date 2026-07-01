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
        'woo_stock_quantity',
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
        'sale_price_sync_status',
        'stock_sync_status',
        'validation_errors_json',
        'validation_warnings_json',
        'last_error',
        'sale_price_last_error',
        'stock_last_error',
        'last_request_summary_json',
        'last_response_summary_json',
        'sale_price_last_request_summary_json',
        'sale_price_last_response_summary_json',
        'stock_last_request_summary_json',
        'stock_last_response_summary_json',
        'attempt_count',
        'sale_price_attempt_count',
        'stock_attempt_count',
        'last_attempted_at',
        'sale_price_last_attempted_at',
        'stock_last_attempted_at',
        'synced_at',
        'sale_price_synced_at',
        'stock_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'proposed_front_payload_json' => 'array',
            'validation_errors_json' => 'array',
            'validation_warnings_json' => 'array',
            'last_request_summary_json' => 'array',
            'last_response_summary_json' => 'array',
            'sale_price_last_request_summary_json' => 'array',
            'sale_price_last_response_summary_json' => 'array',
            'stock_last_request_summary_json' => 'array',
            'stock_last_response_summary_json' => 'array',
            'last_attempted_at' => 'datetime',
            'sale_price_last_attempted_at' => 'datetime',
            'stock_last_attempted_at' => 'datetime',
            'synced_at' => 'datetime',
            'sale_price_synced_at' => 'datetime',
            'stock_synced_at' => 'datetime',
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
