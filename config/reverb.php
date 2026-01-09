<?php

/*
|--------------------------------------------------------------------------
| Reverb Configuration
|--------------------------------------------------------------------------
|
| Production (behind Nginx reverse proxy):
|   - Clients connect to wss://your-domain.com/app (same as APP_URL)
|   - Nginx proxies /app to internal Reverb at 127.0.0.1:6001
|   - No extra configuration needed beyond APP_URL
|
| Development (Reverb runs separately):
|   - Clients connect to ws://localhost:8080/app
|   - Reverb binds to 0.0.0.0:8080 directly
|
| Only REVERB_APP_KEY and REVERB_APP_SECRET are required for production.
|
*/

$appUrl = env('APP_URL', 'http://localhost');
$parsedUrl = parse_url($appUrl) ?: [];
$isProduction = ($parsedUrl['scheme'] ?? 'http') === 'https';

// External connection (frontend WebSocket clients)
// Production: same as APP_URL on standard HTTPS port (443)
// Development: localhost on Reverb's direct port (8080)
$externalHost = $parsedUrl['host'] ?? 'localhost';
$externalPort = $isProduction ? 443 : 8080;
$externalScheme = $isProduction ? 'https' : 'http';

// Internal server binding
// Production (Docker): 127.0.0.1:6001 (behind Nginx)
// Development: 0.0.0.0:8080 (direct access)
$serverHost = env('REVERB_SERVER_HOST', $isProduction ? '127.0.0.1' : '0.0.0.0');
$serverPort = env('REVERB_SERVER_PORT', $isProduction ? 6001 : 8080);

return [

    'default' => 'reverb',

    'servers' => [

        'reverb' => [
            'host' => $serverHost,
            'port' => $serverPort,
            'path' => '',
            'hostname' => $externalHost,
            'options' => [
                'tls' => [],
            ],
            'max_request_size' => 10_000,
            'scaling' => [
                'enabled' => false,
            ],
            'pulse_ingest_interval' => 15,
            'telescope_ingest_interval' => 15,
        ],

    ],

    'apps' => [

        'provider' => 'config',

        'apps' => [
            [
                'key' => env('REVERB_APP_KEY', 'openflare-key'),
                'secret' => env('REVERB_APP_SECRET', 'openflare-secret'),
                'app_id' => 'openflare',
                'options' => [
                    'host' => $externalHost,
                    'port' => $externalPort,
                    'scheme' => $externalScheme,
                    'useTLS' => $externalScheme === 'https',
                ],
                'allowed_origins' => $isProduction ? [$appUrl] : ['*'],
                'ping_interval' => 60,
                'activity_timeout' => 30,
                'max_message_size' => 10_000,
            ],
        ],

    ],

];
