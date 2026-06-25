<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductValidationIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'woo_product_id',
        'woo_variation_id',
        'issue_type',
        'severity',
        'message',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }
}
