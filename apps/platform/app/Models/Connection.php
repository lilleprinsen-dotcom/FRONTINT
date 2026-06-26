<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Connection extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'type',
        'name',
        'base_url',
        'status',
        'last_checked_at',
        'last_test_status',
        'last_http_status',
        'last_response_time_ms',
        'last_error',
        'last_test_metadata',
    ];

    protected function casts(): array
    {
        return [
            'last_checked_at' => 'datetime',
            'last_test_metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(ConnectionCredential::class);
    }

    public function credential(string $type): ?ConnectionCredential
    {
        return $this->credentials->firstWhere('credential_type', $type);
    }
}
