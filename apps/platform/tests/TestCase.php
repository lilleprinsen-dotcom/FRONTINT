<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $this->ensureTestingEnvironmentFileExists();
        $this->forceTestingEnvironment();

        $app = require __DIR__ . '/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    private function ensureTestingEnvironmentFileExists(): void
    {
        $environmentFile = dirname(__DIR__) . '/.env';

        if (file_exists($environmentFile)) {
            return;
        }

        file_put_contents($environmentFile, implode(PHP_EOL, [
            'APP_ENV=testing',
            'APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'DB_CONNECTION=sqlite',
            'DB_DATABASE=:memory:',
            'CACHE_STORE=array',
            'SESSION_DRIVER=array',
            'QUEUE_CONNECTION=sync',
            'OMNIBRIDGE_ENVIRONMENT=staging',
            'OMNIBRIDGE_ALLOW_PRODUCTION_WRITES=false',
            'OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=false',
            '',
        ]));
    }

    private function forceTestingEnvironment(): void
    {
        foreach ($this->testingEnvironment() as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    private function testingEnvironment(): array
    {
        return [
            'APP_ENV' => 'testing',
            'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'OMNIBRIDGE_ENVIRONMENT' => 'staging',
            'OMNIBRIDGE_ALLOW_PRODUCTION_WRITES' => 'false',
            'OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP' => 'false',
        ];
    }
}
