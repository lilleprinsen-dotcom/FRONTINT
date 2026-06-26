<?php

namespace App\Console\Commands;

use App\Models\Connection;
use App\Models\Organization;
use Illuminate\Console\Command;

class PreflightReadOnly extends Command
{
    protected $signature = 'omnibridge:preflight-readonly';

    protected $description = 'Show whether the local/staging portal is ready for live read-only connection tests.';

    public function handle(): int
    {
        $productionWritesEnabled = (bool) config('omnibridge.allow_production_writes');
        $liveHttpEnabled = (bool) config('omnibridge.allow_connection_test_http');
        $appEnv = (string) config('app.env');
        $openApiPath = base_path('../../docs/vendor/front-systems/openapi/frontsystems.openapi.json');
        $connectionCount = Connection::query()->count();
        $connectionsWithCredentials = Connection::query()->whereHas('credentials')->count();

        $this->line('OmniBridge live read-only preflight');
        $this->line('');
        $this->line('APP_ENV: ' . $appEnv);
        $this->line('OMNIBRIDGE_ENVIRONMENT: ' . config('omnibridge.environment'));
        $this->line('OMNIBRIDGE_ALLOW_PRODUCTION_WRITES: ' . ($productionWritesEnabled ? 'true' : 'false'));
        $this->line('OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP: ' . ($liveHttpEnabled ? 'true' : 'false'));
        $this->line('Database connection: ' . config('database.default'));
        $this->line('Organizations: ' . Organization::query()->count());
        $this->line('Connections: ' . $connectionCount);
        $this->line('Front OpenAPI file: ' . (is_file($openApiPath) ? 'present' : 'missing'));
        $this->line('Connections with credentials configured: ' . $connectionsWithCredentials);
        $this->line('');

        if ($productionWritesEnabled) {
            $this->warn('WARNING: Production writes are enabled. Disable OMNIBRIDGE_ALLOW_PRODUCTION_WRITES before read-only testing.');
        }

        if ($appEnv === 'production') {
            $this->warn('WARNING: APP_ENV=production. Use local/staging for the first live read-only tests.');
        }

        if (! $liveHttpEnabled) {
            $this->line('Live HTTP tests are currently disabled. Dashboard actions will return skipped/safe mode.');
        }

        if (! $productionWritesEnabled && $appEnv !== 'production') {
            $this->info('Safe for read-only tests: production writes are disabled and this is not APP_ENV=production.');

            return self::SUCCESS;
        }

        $this->error('Not safe for read-only tests until the warnings above are resolved.');

        return self::FAILURE;
    }
}
