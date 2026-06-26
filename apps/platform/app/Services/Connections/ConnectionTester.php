<?php

namespace App\Services\Connections;

use App\Models\Connection;
use App\Services\Safety\StagingSafety;
use Illuminate\Support\Facades\Http;
use Throwable;

class ConnectionTester
{
    public function __construct(
        private readonly StagingSafety $safety,
    ) {
    }

    public function test(Connection $connection): array
    {
        $connection->loadMissing('credentials', 'organization');

        $missing = $this->missingRequirements($connection);

        if ($missing !== []) {
            return [
                'status' => 'incomplete',
                'message' => 'Connection settings are incomplete.',
                'missing' => $missing,
                'http_checked' => false,
            ];
        }

        if (! $this->safety->connectionHttpTestsAllowed()) {
            return [
                'status' => 'configured',
                'message' => 'Credentials are stored. Live HTTP checks are disabled for staging safety.',
                'missing' => [],
                'http_checked' => false,
            ];
        }

        return $this->performReadOnlyHttpCheck($connection);
    }

    private function missingRequirements(Connection $connection): array
    {
        $missing = [];

        if (! $connection->base_url) {
            $missing[] = 'base_url';
        }

        foreach ($this->requiredCredentialTypes($connection->type) as $credentialType) {
            if (! $connection->credential($credentialType)) {
                $missing[] = "credential:{$credentialType}";
            }
        }

        return $missing;
    }

    private function requiredCredentialTypes(string $connectionType): array
    {
        return match ($connectionType) {
            'woocommerce' => ['consumer_key', 'consumer_secret'],
            'front' => ['api_key'],
            'webtoffee_adapter' => ['shared_secret'],
            'dintero', 'stripe' => [],
            default => [],
        };
    }

    private function performReadOnlyHttpCheck(Connection $connection): array
    {
        try {
            $response = Http::timeout(10)->get(rtrim((string) $connection->base_url, '/'));

            return [
                'status' => $response->successful() ? 'reachable' : 'http_error',
                'message' => $response->successful()
                    ? 'Base URL responded to a read-only check.'
                    : 'Base URL responded with a non-success status.',
                'http_status' => $response->status(),
                'http_checked' => true,
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'unreachable',
                'message' => 'Base URL could not be reached by the read-only check.',
                'error_class' => $exception::class,
                'http_checked' => true,
            ];
        }
    }
}
