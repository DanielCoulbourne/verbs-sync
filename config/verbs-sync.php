<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Verbs Sync Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for the Verbs Sync package.
    | It allows a Laravel app using verbs (verbs.thunk.dev) to sync events
    | from a remote source application.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Source Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the remote source application to pull events from.
    |
    */
    'source' => [
        'url' => env('VERBS_SYNC_SOURCE_URL'),
        'api_token' => env('VERBS_SYNC_API_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for which events to sync and how to handle them.
    |
    */
    'events' => [
        'include' => explode(',', env('VERBS_SYNC_INCLUDE_EVENTS', '*')),
        'exclude' => explode(',', env('VERBS_SYNC_EXCLUDE_EVENTS', '')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Syncing Options
    |--------------------------------------------------------------------------
    |
    | Options that control how events are synced.
    |
    */
    'options' => [
        'batch_size' => env('VERBS_SYNC_BATCH_SIZE', 100),
        'retry_attempts' => env('VERBS_SYNC_RETRY_ATTEMPTS', 3),
    ],
];
