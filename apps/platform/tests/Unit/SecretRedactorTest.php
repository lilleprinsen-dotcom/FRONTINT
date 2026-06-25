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
            'nested' => [
                'token' => 'secret-token',
                'safe' => 'visible',
            ],
        ]);

        $this->assertSame('[redacted]', $redacted['consumer_secret']);
        $this->assertSame('[redacted]', $redacted['nested']['token']);
        $this->assertSame('visible', $redacted['nested']['safe']);
    }
}
