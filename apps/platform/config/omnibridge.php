<?php

return [
    'environment' => env('OMNIBRIDGE_ENVIRONMENT', 'staging'),
    'allow_production_writes' => (bool) env('OMNIBRIDGE_ALLOW_PRODUCTION_WRITES', false),
    'redact_log_secrets' => (bool) env('OMNIBRIDGE_REDACT_LOG_SECRETS', true),

    'webhooks' => [
        'woocommerce_signature_header' => 'X-WC-Webhook-Signature',
        'front_signature_header' => 'X-Omnibridge-Front-Signature',
        'front_token_header' => 'X-Omnibridge-Webhook-Token',
    ],

    'queues' => [
        'events' => env('OMNIBRIDGE_EVENTS_QUEUE', 'events'),
        'sync' => env('OMNIBRIDGE_SYNC_QUEUE', 'sync'),
    ],
];
