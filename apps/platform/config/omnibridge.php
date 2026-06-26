<?php

return [
    'environment' => env('OMNIBRIDGE_ENVIRONMENT', 'staging'),
    'allow_production_writes' => (bool) env('OMNIBRIDGE_ALLOW_PRODUCTION_WRITES', false),
    'allow_connection_test_http' => (bool) env('OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP', false),
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

    'connection_types' => [
        'woocommerce' => 'WooCommerce',
        'front_systems' => 'Front Systems',
        'front' => 'Front Systems (legacy)',
        'webtoffee_adapter' => 'WebToffee adapter',
        'dintero' => 'Dintero',
        'stripe' => 'Stripe',
    ],

    'front_systems' => [
        'default_base_url' => env('FRONT_SYSTEMS_BASE_URL', 'https://frontsystemsapis.frontsystems.no/restapi/V2'),
    ],
];
