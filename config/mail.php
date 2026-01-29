<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message.
    |
    | Use "log" for development (emails logged to the default log channel)
    | and "resend" for production (requires RESEND_API_KEY to be set).
    | production (requires RESEND_API_KEY to be set).
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | OpenFlare supports Resend for production email delivery and log/array
    | for development and testing.
    |
    */

    'mailers' => [

        'resend' => [
            'transport' => 'resend',
        ],

        'log' => [
            'transport' => 'log',
            'channel' => null,
        ],

        'array' => [
            'transport' => 'array',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => 'OpenFlare',
    ],

];
