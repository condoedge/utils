<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Analytics Enabled
    |--------------------------------------------------------------------------
    |
    | Enable or disable page view logging. When disabled, no page views
    | will be logged to the database.
    |
    */

    'enabled' => env('ANALYTICS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Page View Log Model
    |--------------------------------------------------------------------------
    |
    | The model class used for page view logging. Override this if you
    | need to use a custom model with additional fields or behavior.
    |
    */

    'page-view-log-model' => \Condoedge\Utils\Models\Analytics\PageViewLog::class,

    /*
    |--------------------------------------------------------------------------
    | Log API Requests
    |--------------------------------------------------------------------------
    |
    | By default, API requests are not logged. Set this to true to log
    | API endpoint requests (routes starting with 'api/*').
    |
    */

    'log_api_requests' => env('ANALYTICS_LOG_API_REQUESTS', false),

    /*
    |--------------------------------------------------------------------------
    | Excluded Paths
    |--------------------------------------------------------------------------
    |
    | List of URL patterns to exclude from page view logging.
    | Supports wildcards (e.g., 'admin/*', '_debugbar/*').
    |
    */

    'excluded_paths' => [
        '_debugbar/*',
        'telescope/*',
        'horizon/*',
        'livewire/*',
        '*/message',
        'admin/analytics/*', // Don't log analytics pages themselves
        '*.js',
        '*.css',
        '*.map',
        '*.ico',
        '*.png',
        '*.jpg',
        '*.jpeg',
        '*.gif',
        '*.svg',
        '*.woff',
        '*.woff2',
        '*.ttf',
        '*.eot',
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Routes
    |--------------------------------------------------------------------------
    |
    | List of route names to exclude from page view logging.
    |
    */

    'excluded_routes' => [
        'debugbar.*',
        'telescope.*',
        'horizon.*',
        'livewire.message',
        'livewire.upload-file',
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for the Google Analytics Dashboard.
    |
    */

    'dashboard' => [
        // Number of days to show by default
        'default_date_range' => 30,

        // Number of items per page in logs table
        'logs_per_page' => 50,

        // Refresh interval for realtime feed (in seconds)
        'realtime_refresh_interval' => 30,

        // Cache duration for dashboard metrics (in minutes)
        'cache_duration' => 5,

        // Top pages limit
        'top_pages_limit' => 10,

        // Top users limit
        'top_users_limit' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Retention
    |--------------------------------------------------------------------------
    |
    | How long to keep page view logs in the database.
    | Older logs will be automatically deleted.
    |
    | Set to null to keep logs indefinitely.
    | Set to a number to keep logs for that many days.
    |
    */

    'data_retention_days' => env('ANALYTICS_DATA_RETENTION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Privacy Settings
    |--------------------------------------------------------------------------
    |
    | Privacy-related settings for analytics.
    |
    */

    'privacy' => [
        // Anonymize IP addresses (last octet set to 0)
        'anonymize_ip' => env('ANALYTICS_ANONYMIZE_IP', false),

        // Hash user IDs before storing
        'hash_user_ids' => env('ANALYTICS_HASH_USER_IDS', true),

        // Respect Do Not Track browser setting
        'respect_do_not_track' => env('ANALYTICS_RESPECT_DNT', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Performance-related settings for analytics logging.
    |
    */

    'performance' => [
        // Log page views asynchronously (doesn't block requests)
        'async_logging' => env('ANALYTICS_ASYNC_LOGGING', true),

        // Batch insert multiple logs at once (for high-traffic sites)
        'batch_logging' => env('ANALYTICS_BATCH_LOGGING', false),

        // Batch size (number of logs to batch before inserting)
        'batch_size' => env('ANALYTICS_BATCH_SIZE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Chart Colors
    |--------------------------------------------------------------------------
    |
    | Colors used in dashboard charts.
    |
    */

    'chart_colors' => [
        'primary' => '#3B82F6',     // Blue
        'success' => '#10B981',     // Green
        'warning' => '#F59E0B',     // Orange
        'danger' => '#EF4444',      // Red
        'info' => '#06B6D4',        // Cyan
        'secondary' => '#6B7280',   // Gray
    ],

];
