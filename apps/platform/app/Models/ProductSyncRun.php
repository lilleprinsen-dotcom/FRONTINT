<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductSyncRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'product_sync_profile_id',
        'created_by_user_id',
        'status',
        'mode',
        'total_candidates',
        'total_ready',
        'total_blocked',
        'total_synced',
        'total_failed',
        'total_skipped',
        'started_at',
        'finished_at',
        'summary_json',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'summary_json' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ProductSyncProfile::class, 'product_sync_profile_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductSyncRunItem::class);
    }
}
