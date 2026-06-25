<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectionCredential extends Model
{
    use HasFactory;

    protected $fillable = [
        'connection_id',
        'credential_type',
        'encrypted_payload',
        'redacted_hint',
        'rotated_at',
    ];

    protected function casts(): array
    {
        return [
            'encrypted_payload' => 'encrypted:array',
            'rotated_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }
}
