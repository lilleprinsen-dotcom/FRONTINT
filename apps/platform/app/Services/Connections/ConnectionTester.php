<?php

namespace App\Services\Connections;

use App\Models\Connection;
use App\Services\FrontSystems\FrontSystemsReadOnlyClient;
use App\Services\Safety\StagingSafety;
use App\Services\WooCommerce\WooCommerceReadOnlyClient;
use Illuminate\Support\Facades\Http;
use Throwable;

class ConnectionTester
{
    public function __construct(
        private readonly StagingSafety $safety,
        private readonly WooCommerceReadOnlyClient $wooCommerce,
        private readonly FrontSystemsReadOnlyClient $frontSystems,
    ) {
    }

    public function test(Connection $connection): array
    {
        $connection->loadMissing('credentials', 'organization');

        $missing = $this->missingRequirements($connection);

        if ($missing !== []) {
            return [
                'status' => 'failed',
                'message' => 'Connection settings are incomplete.',
                'missing' => $missing,
                'http_checked' => false,
                'checked_at' => now()->toISOString(),
                'last_error' => 'Missing required settings: ' . implode(', ', $missing),
            ];
        }

        if (! $this->safety->connectionHttpTestsAllowed()) {
            return [
                'status' => 'skipped',
                'message' => 'Safe mode: credentials are stored, but live HTTP checks are disabled.',
                'missing' => [],
                'http_checked' => false,
                'checked_at' => now()->toISOString(),
                'read_only' => true,
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
            'front', 'front_systems' => ['api_key'],
            'webtoffee_adapter' => ['shared_secret'],
            'dintero', 'stripe' => [],
            default => [],
        };
    }

    private function performReadOnlyHttpCheck(Connection $connection): array
    {
        if ($connection->type === 'woocommerce') {
            return $this->wooCommerce->test($connection);
        }

        if (in_array($connection->type, ['front', 'front_systems'], true)) {
            return $this->frontSystems->test($connection);
        }

        try {
            $startedAt = microtime(true);
            $response = Http::timeout(10)->get(rtrim((string) $connection->base_url, '/'));

            return [
                'status' => $response->successful() ? 'success' : 'failed',
                'message' => $response->successful()
                    ? 'Base URL responded to a read-only check.'
                    : 'Base URL responded with a non-success status.',
                'http_status' => $response->status(),
                'http_checked' => true,
                'read_only' => true,
                'response_time_ms' => $this->responseTimeMs($startedAt),
                'checked_at' => now()->toISOString(),
                'last_error' => $response->successful() ? null : 'HTTP ' . $response->status(),
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'failed',
                'message' => 'Base URL could not be reached by the read-only check.',
                'error_class' => $exception::class,
                'http_checked' => true,
                'read_only' => true,
                'response_time_ms' => isset($startedAt) ? $this->responseTimeMs($startedAt) : null,
                'checked_at' => now()->toISOString(),
                'last_error' => $exception::class,
            ];
        }
    }

    private function responseTimeMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
