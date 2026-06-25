<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockLedger extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'stock_ledger';

    protected $fillable = [
        'organization_id',
        'product_mapping_id',
        'source_system',
        'movement_type',
        'quantity_delta',
        'physical_quantity_after',
        'reserved_quantity_after',
        'available_quantity_after',
        'source_reference',
        'idempotency_key',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
