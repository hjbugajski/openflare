<?php

/*
|--------------------------------------------------------------------------
| Broadcasting Configuration
|--------------------------------------------------------------------------
|
| Laravel broadcasts events to Reverb's internal HTTP API.
| Production: 127.0.0.1:6001 (behind Nginx)
| Development: 127.0.0.1:8080 (direct)
|
*/

$isProduction = str_starts_with(env('APP_URL', 'http://localhost'), 'https://');
$serverPort = env('REVERB_SERVER_PORT', $isProduction ? 6001 : 8080);

return [

    'default' => 'reverb',

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY', 'openflare-key'),
            'secret' => env('REVERB_APP_SECRET', 'openflare-secret'),
            'app_id' => 'openflare',
            'options' => [
                'host' => '127.0.0.1',
                'port' => $serverPort,
                'scheme' => 'http',
                'useTLS' => false,
            ],
        ],

    ],

];
