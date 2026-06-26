<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSyncPreviewPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'created_by_user_id',
        'woo_connection_id',
        'front_connection_id',
        'status',
        'selected_count',
        'summary_json',
        'plan_json',
        'validation_json',
    ];

    protected function casts(): array
    {
        return [
            'summary_json' => 'array',
            'plan_json' => 'array',
            'validation_json' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function wooConnection(): BelongsTo
    {
        return $this->belongsTo(Connection::class, 'woo_connection_id');
    }

    public function frontConnection(): BelongsTo
    {
        return $this->belongsTo(Connection::class, 'front_connection_id');
    }
}
