<?php

namespace App\Services\Safety;

use App\Models\Organization;

class StagingSafety
{
    public function productionWritesAllowed(?Organization $organization = null): bool
    {
        if (! (bool) config('omnibridge.allow_production_writes')) {
            return false;
        }

        return $organization?->environment === 'production';
    }

    public function connectionHttpTestsAllowed(): bool
    {
        return (bool) config('omnibridge.allow_connection_test_http');
    }

    public function environmentLabel(?Organization $organization = null): string
    {
        return $organization?->environment ?: (string) config('omnibridge.environment', 'staging');
    }
}
