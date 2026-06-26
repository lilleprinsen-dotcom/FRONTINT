<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'source_system',
        'event_type',
        'source_event_id',
        'idempotency_key',
        'payload_json',
        'metadata_json',
        'status',
        'received_at',
        'processed_at',
        'error_class',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'metadata_json' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function jobRuns(): HasMany
    {
        return $this->hasMany(JobRun::class);
    }
}
