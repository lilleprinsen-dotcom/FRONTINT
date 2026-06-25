<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'product_mapping_id',
        'woo_order_id',
        'front_reservation_id',
        'quantity',
        'status',
        'expires_at',
        'released_at',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }
}
