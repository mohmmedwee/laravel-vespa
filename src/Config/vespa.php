<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Vespa API URL
    |--------------------------------------------------------------------------
    |
    | This is the base URL for your Vespa instance. You can configure it via
    | the environment variable `VESPA_URL`. Make sure to set this value
    | in your .env file for different environments (local, staging, production).
    |
    */

    'url' => env('VESPA_URL', 'http://localhost:8080'),

    /*
    |--------------------------------------------------------------------------
    | Vespa API Key
    |--------------------------------------------------------------------------
    |
    | If your Vespa instance requires an API key for authentication, you can
    | set it here via the environment variable `VESPA_API_KEY`. Leave it null
    | if no authentication is required.
    |
    */

    'api_key' => env('VESPA_API_KEY', null),

    /*
    |--------------------------------------------------------------------------
    | HTTP Request Timeout
    |--------------------------------------------------------------------------
    |
    | This value defines the default timeout for HTTP requests made by the
    | VespaClient. You can configure it via the `VESPA_HTTP_TIMEOUT` environment
    | variable. The timeout is specified in seconds.
    |
    */

    'timeout' => env('VESPA_HTTP_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Rate Limits
    |--------------------------------------------------------------------------
    |
    | These values define the rate limit for the VespaClient. The `rate_limit`
    | is the maximum number of requests per minute, and `throttle_limit`
    | defines the maximum number of simultaneous requests. You can configure
    | these values via the respective environment variables.
    |
    */

    'rate_limit' => env('VESPA_RATE_LIMIT', 100),
    'throttle_limit' => env('VESPA_THROTTLE_LIMIT', 10),

    /*
    |--------------------------------------------------------------------------
    | Default Language
    |--------------------------------------------------------------------------
    |
    | This value defines the default language used for queries. You can override
    | this value for individual queries, but this is the global default.
    |
    */

    'language' => env('VESPA_DEFAULT_LANGUAGE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Logging Channels
    |--------------------------------------------------------------------------
    |
    | These values define the logging channels used by the VespaClient for
    | general logs, audit logs, and error logs. You can configure custom
    | logging channels via your logging configuration in config/logging.php.
    |
    */

    'log_channel' => env('VESPA_LOG_CHANNEL', 'default'),
    'audit_log_channel' => env('VESPA_AUDIT_LOG_CHANNEL', 'audit'),
    'error_log_channel' => env('VESPA_ERROR_LOG_CHANNEL', 'vespa_errors'),
];
