<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'woo_customer_id',
        'front_customer_id',
        'email_hash',
        'phone_hash',
        'match_confidence',
    ];
}
