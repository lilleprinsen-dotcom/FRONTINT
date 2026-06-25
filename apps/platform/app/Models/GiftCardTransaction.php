<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GiftCardTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'gift_card_code_hash',
        'operation',
        'amount',
        'currency',
        'source_system',
        'source_reference',
        'status',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }
}
