<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FrontWebhookRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'connection_id',
        'webhook_endpoint_id',
        'front_webhook_id',
        'webhook_type',
        'callback_url',
        'status',
        'request_summary_json',
        'response_summary_json',
        'last_error',
        'registered_at',
    ];

    protected function casts(): array
    {
        return [
            'request_summary_json' => 'array',
            'response_summary_json' => 'array',
            'registered_at' => 'datetime',
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

    public function webhookEndpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class);
    }
}
