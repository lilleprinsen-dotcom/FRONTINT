<?php

namespace Tests\Unit;

use App\Services\Security\SecretRedactor;
use Tests\TestCase;

class SecretRedactorTest extends TestCase
{
    public function test_it_redacts_sensitive_keys_recursively(): void
    {
        $redacted = (new SecretRedactor())->redact([
            'consumer_secret' => 'abc',
            'x-api-key' => 'front-key',
            'Authorization' => 'Bearer abc',
            'nested' => [
                'api_key' => 'nested-key',
                'token' => 'secret-token',
                'safe' => 'visible',
            ],
            'normal_field' => 'visible',
        ]);

        $this->assertSame('[redacted]', $redacted['consumer_secret']);
        $this->assertSame('[redacted]', $redacted['x-api-key']);
        $this->assertSame('[redacted]', $redacted['Authorization']);
        $this->assertSame('[redacted]', $redacted['nested']['api_key']);
        $this->assertSame('[redacted]', $redacted['nested']['token']);
        $this->assertSame('visible', $redacted['nested']['safe']);
        $this->assertSame('visible', $redacted['normal_field']);
    }
}
