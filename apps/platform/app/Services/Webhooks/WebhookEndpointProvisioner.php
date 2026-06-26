<?php

namespace App\Services\Webhooks;

use App\Models\Organization;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Str;

class WebhookEndpointProvisioner
{
    public function ensureDefaults(Organization $organization): void
    {
        foreach (['woocommerce', 'front'] as $sourceSystem) {
            WebhookEndpoint::query()->firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'source_system' => $sourceSystem,
                ],
                [
                    'path_token' => Str::random(48),
                    'status' => 'active',
                ],
            );
        }
    }
}
