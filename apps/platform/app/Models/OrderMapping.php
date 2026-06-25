<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'woo_order_id',
        'front_order_id',
        'front_receipt_id',
        'source',
        'status',
        'idempotency_key',
    ];
}
