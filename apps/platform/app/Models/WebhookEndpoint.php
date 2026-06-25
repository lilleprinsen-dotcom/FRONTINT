<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookEndpoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'source_system',
        'path_token',
        'encrypted_secret',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'encrypted_secret' => 'encrypted',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
