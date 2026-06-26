<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectionDiscoverySnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'connection_id',
        'source_system',
        'discovery_type',
        'status',
        'summary_json',
        'sample_json',
        'error_message',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'summary_json' => 'array',
            'sample_json' => 'array',
            'checked_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }
}
