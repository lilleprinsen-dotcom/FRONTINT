<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FrontSaleImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'event_id',
        'order_mapping_id',
        'status',
        'front_sale_id',
        'front_receipt_id',
        'idempotency_key',
        'sale_time',
        'currency',
        'total_amount',
        'payload_summary_json',
        'line_items_json',
        'woo_order_payload_json',
        'last_request_summary_json',
        'last_response_summary_json',
        'error_message',
        'attempt_count',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'sale_time' => 'datetime',
            'total_amount' => 'decimal:2',
            'payload_summary_json' => 'array',
            'line_items_json' => 'array',
            'woo_order_payload_json' => 'array',
            'last_request_summary_json' => 'array',
            'last_response_summary_json' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function orderMapping(): BelongsTo
    {
        return $this->belongsTo(OrderMapping::class);
    }
}
