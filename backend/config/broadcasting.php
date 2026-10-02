<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This option defines the default broadcaster that is utilized to write
    | messages to your broadcast channels. The value provided here
    | should match one of the broadcasters defined below.
    |
    */

    'default' => env('BROADCAST_CONNECTION', 'reverb'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the broadcast configurations for your
    | application. Each configuration represents a different broadcaster
    | that may be used to send messages to your broadcast channels.
    |
    */

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'host' => env('REVERB_HOST', '127.0.0.1'),
            'port' => env('REVERB_PORT', 8080),
            'scheme' => env('REVERB_SCHEME', 'http'),
            'app_id' => env('REVERB_APP_ID'),
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'path' => '/app',
            'curl_options' => [],
        ],

        'log' => [
            'driver' => 'log',
            'channel' => null,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcast Channels
    |--------------------------------------------------------------------------
    |
    | Here you may define which channels can be broadcasted to which
    | broadcasters. This helps ensure that only authorized channels
    | can be sent to each respective broadcaster.
    |
    */

    'channels' => [

    ],

];