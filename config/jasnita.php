<?php

return [

    'dsn' => env('JASNITA_MONITOR_DSN', env('JASNITA_MONITOR_DSN')),

    // capture release as git sha
    // 'release' => trim(exec('git --git-dir ' . base_path('.git') . ' log --pretty="%h" -n1 HEAD')),

    // When left empty or `null` the Laravel environment will be used
    'environment' => env('JASNITA_MONITOR_ENVIRONMENT'),

    'breadcrumbs' => [
        // Capture Laravel logs in breadcrumbs
        'logs' => true,

        // Capture SQL queries in breadcrumbs
        'sql_queries' => true,

        // Capture bindings on SQL queries logged in breadcrumbs
        'sql_bindings' => true,

        // Capture queue job information in breadcrumbs
        'queue_info' => true,

        // Capture command information in breadcrumbs
        'command_info' => true,
    ],

    'tracing' => [
        // Trace queue jobs as their own transactions
        'queue_job_transactions' => env('JASNITA_MONITOR_TRACE_QUEUE_ENABLED', false),

        // Capture queue jobs as spans when executed on the sync driver
        'queue_jobs' => true,

        // Capture SQL queries as spans
        'sql_queries' => true,

        // Try to find out where the SQL query originated from and add it to the query spans
        'sql_origin' => true,

        // Capture views as spans
        'views' => true,

        // Indicates if the tracing integrations supplied by Jasnita should be loaded
        'default_integrations' => true,
    ],

    // @see: (dokumentasi hulu)
    'send_default_pii' => env('JASNITA_MONITOR_SEND_DEFAULT_PII', false),

    'traces_sample_rate' => (float)(env('JASNITA_MONITOR_TRACES_SAMPLE_RATE', 0.0)),

    'controllers_base_namespace' => env('JASNITA_MONITOR_CONTROLLERS_BASE_NAMESPACE', 'App\\Http\\Controllers'),

];
