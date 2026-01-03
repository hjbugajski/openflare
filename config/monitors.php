<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Test Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, monitor checks will not be dispatched. This is useful
    | for testing the frontend with seed data without triggering real
    | HTTP requests to monitor URLs.
    |
    */

    'test_mode' => env('MONITORS_TEST_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Dispatch Limit
    |--------------------------------------------------------------------------
    |
    | Maximum number of monitor checks to dispatch per scheduler run.
    | This prevents stampeding when many monitors become due at once.
    |
    */

    'dispatch_limit' => env('MONITORS_DISPATCH_LIMIT', 500),

    /*
    |--------------------------------------------------------------------------
    | User Agent
    |--------------------------------------------------------------------------
    |
    | The User-Agent header sent when performing HTTP checks.
    |
    */

    'user_agent' => env('MONITORS_USER_AGENT', 'OpenFlare Monitor/1.0'),

    /*
    |--------------------------------------------------------------------------
    | Data Retention
    |--------------------------------------------------------------------------
    |
    | Number of days to retain monitor check history.
    |
    */

    'retention_days' => env('MONITORS_RETENTION_DAYS', 30),

];
