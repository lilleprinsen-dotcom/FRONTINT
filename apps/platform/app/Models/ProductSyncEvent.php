<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSyncEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'source_system',
        'event_type',
        'woo_product_id',
        'woo_variation_id',
        'woo_item_key',
        'dedupe_key',
        'status',
        'priority',
        'payload_summary_json',
        'received_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_summary_json' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
