<?php

/**
 * Jasnita Laravel SDK configuration file.
 *
 * @see (dokumentasi hulu)
 */
return [

    // @see (dokumentasi hulu)
    'dsn' => env('JASNITA_MONITOR_DSN', env('JASNITA_MONITOR_DSN')),

    // @see https://spotlightjs.com/
    // 'spotlight' => env('JASNITA_MONITOR_SPOTLIGHT', false),

    // @see: (dokumentasi hulu)
    // 'logger' => Jasnita\Monitor\Sdk\Logger\DebugFileLogger::class, // By default this will log to `storage_path('logs/jasnita-monitor.log')`

    // The release version of your application
    // Example with dynamic git hash: trim(exec('git --git-dir ' . base_path('.git') . ' log --pretty="%h" -n1 HEAD'))
    'release' => env('JASNITA_MONITOR_RELEASE'),

    // When left empty or `null` the Laravel environment will be used (usually discovered from `APP_ENV` in your `.env`)
    'environment' => env('JASNITA_MONITOR_ENVIRONMENT'),

    // Override the organization ID used for trace continuation checks.
    'org_id' => env('JASNITA_MONITOR_ORG_ID') === null ? null : (int) env('JASNITA_MONITOR_ORG_ID'),

    // @see: (dokumentasi hulu)
    'sample_rate' => env('JASNITA_MONITOR_SAMPLE_RATE') === null ? 1.0 : (float) env('JASNITA_MONITOR_SAMPLE_RATE'),

    // @see: (dokumentasi hulu)
    'traces_sample_rate' => env('JASNITA_MONITOR_TRACES_SAMPLE_RATE') === null ? null : (float) env('JASNITA_MONITOR_TRACES_SAMPLE_RATE'),

    // @see: (dokumentasi hulu)
    'profiles_sample_rate' => env('JASNITA_MONITOR_PROFILES_SAMPLE_RATE') === null ? null : (float) env('JASNITA_MONITOR_PROFILES_SAMPLE_RATE'),

    // Only continue incoming traces when the organization IDs are compatible with this SDK instance.
    'strict_trace_continuation' => env('JASNITA_MONITOR_STRICT_TRACE_CONTINUATION', false),

    // @see: (dokumentasi hulu)
    'enable_logs' => env('JASNITA_MONITOR_ENABLE_LOGS', false),

    // @see: (dokumentasi hulu)
    'enable_metrics' => env('JASNITA_MONITOR_ENABLE_METRICS', true),

    // @see: (dokumentasi hulu)
    'log_flush_threshold' => env('JASNITA_MONITOR_LOG_FLUSH_THRESHOLD') === null ? null : (int) env('JASNITA_MONITOR_LOG_FLUSH_THRESHOLD'),

    // The minimum log level that will be sent to Jasnita as logs using the `jasnita_logs` logging channel
    'logs_channel_level' => env('JASNITA_MONITOR_LOG_LEVEL', env('JASNITA_MONITOR_LOGS_LEVEL', env('LOG_LEVEL', 'debug'))),

    // @see: (dokumentasi hulu)
    'send_default_pii' => env('JASNITA_MONITOR_SEND_DEFAULT_PII', false),

    // @see: (dokumentasi hulu)
    // 'ignore_exceptions' => [],

    // @see: (dokumentasi hulu)
    'ignore_transactions' => [
        // Ignore Laravel's default health URL
        '/up',
    ],

    // Breadcrumb specific configuration
    'breadcrumbs' => [
        // Capture Laravel logs as breadcrumbs
        'logs' => env('JASNITA_MONITOR_BREADCRUMBS_LOGS_ENABLED', true),

        // Capture Laravel cache events (hits, writes etc.) as breadcrumbs
        'cache' => env('JASNITA_MONITOR_BREADCRUMBS_CACHE_ENABLED', true),

        // Capture Livewire components like routes as breadcrumbs
        'livewire' => env('JASNITA_MONITOR_BREADCRUMBS_LIVEWIRE_ENABLED', true),

        // Capture SQL queries as breadcrumbs
        'sql_queries' => env('JASNITA_MONITOR_BREADCRUMBS_SQL_QUERIES_ENABLED', true),

        // Capture SQL query bindings (parameters) in SQL query breadcrumbs
        'sql_bindings' => env('JASNITA_MONITOR_BREADCRUMBS_SQL_BINDINGS_ENABLED', false),

        // Capture queue job information as breadcrumbs
        'queue_info' => env('JASNITA_MONITOR_BREADCRUMBS_QUEUE_INFO_ENABLED', true),

        // Capture command information as breadcrumbs
        'command_info' => env('JASNITA_MONITOR_BREADCRUMBS_COMMAND_JOBS_ENABLED', true),

        // Capture HTTP client request information as breadcrumbs
        'http_client_requests' => env('JASNITA_MONITOR_BREADCRUMBS_HTTP_CLIENT_REQUESTS_ENABLED', true),

        // Capture send notifications as breadcrumbs
        'notifications' => env('JASNITA_MONITOR_BREADCRUMBS_NOTIFICATIONS_ENABLED', true),
    ],

    // Performance monitoring specific configuration
    'tracing' => [
        // Trace queue jobs as their own transactions (this enables tracing for queue jobs)
        'queue_job_transactions' => env('JASNITA_MONITOR_TRACE_QUEUE_ENABLED', true),

        // Capture queue jobs as spans when executed on the sync driver
        'queue_jobs' => env('JASNITA_MONITOR_TRACE_QUEUE_JOBS_ENABLED', true),

        // Capture SQL queries as spans
        'sql_queries' => env('JASNITA_MONITOR_TRACE_SQL_QUERIES_ENABLED', true),

        // Capture SQL query bindings (parameters) in SQL query spans
        'sql_bindings' => env('JASNITA_MONITOR_TRACE_SQL_BINDINGS_ENABLED', false),

        // Capture where the SQL query originated from on the SQL query spans
        'sql_origin' => env('JASNITA_MONITOR_TRACE_SQL_ORIGIN_ENABLED', true),

        // Define a threshold in milliseconds for SQL queries to resolve their origin
        'sql_origin_threshold_ms' => env('JASNITA_MONITOR_TRACE_SQL_ORIGIN_THRESHOLD_MS', 100),

        // Capture views rendered as spans
        'views' => env('JASNITA_MONITOR_TRACE_VIEWS_ENABLED', true),

        // Capture Livewire components as spans
        'livewire' => env('JASNITA_MONITOR_TRACE_LIVEWIRE_ENABLED', true),

        // Capture HTTP client requests as spans
        'http_client_requests' => env('JASNITA_MONITOR_TRACE_HTTP_CLIENT_REQUESTS_ENABLED', true),

        // Capture Laravel cache events (hits, writes etc.) as spans
        'cache' => env('JASNITA_MONITOR_TRACE_CACHE_ENABLED', true),

        // Capture Redis operations as spans (this enables Redis events in Laravel)
        'redis_commands' => env('JASNITA_MONITOR_TRACE_REDIS_COMMANDS', false),

        // Capture where the Redis command originated from on the Redis command spans
        'redis_origin' => env('JASNITA_MONITOR_TRACE_REDIS_ORIGIN_ENABLED', true),

        // Capture send notifications as spans
        'notifications' => env('JASNITA_MONITOR_TRACE_NOTIFICATIONS_ENABLED', true),

        // Enable tracing for requests without a matching route (404's)
        'missing_routes' => env('JASNITA_MONITOR_TRACE_MISSING_ROUTES_ENABLED', false),

        // Configures if the performance trace should continue after the response has been sent to the user until the application terminates
        // This is required to capture any spans that are created after the response has been sent like queue jobs dispatched using `dispatch(...)->afterResponse()` for example
        'continue_after_response' => env('JASNITA_MONITOR_TRACE_CONTINUE_AFTER_RESPONSE', true),

        // Capture AI agent interactions as spans (requires laravel/ai)
        'gen_ai' => env('JASNITA_MONITOR_TRACE_GEN_AI_ENABLED', true),

        // Capture AI invoke_agent spans
        'gen_ai_invoke_agent' => env('JASNITA_MONITOR_TRACE_GEN_AI_INVOKE_AGENT_ENABLED', true),

        // Capture AI chat spans
        'gen_ai_chat' => env('JASNITA_MONITOR_TRACE_GEN_AI_CHAT_ENABLED', true),

        // Capture AI execute_tool spans
        'gen_ai_execute_tool' => env('JASNITA_MONITOR_TRACE_GEN_AI_EXECUTE_TOOL_ENABLED', true),

        // Capture AI embeddings spans
        'gen_ai_embeddings' => env('JASNITA_MONITOR_TRACE_GEN_AI_EMBEDDINGS_ENABLED', true),

        // Enable the tracing integrations supplied by Jasnita (recommended)
        'default_integrations' => env('JASNITA_MONITOR_TRACE_DEFAULT_INTEGRATIONS_ENABLED', true),
    ],

];
