<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PANÉTA Platform Configuration
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Instruction Signing Secret
    |--------------------------------------------------------------------------
    | Used for HMAC-SHA256 signing of payment instructions.
    */
    'instruction_secret' => env('PANETA_INSTRUCTION_SECRET', 'change-me-in-production'),

    /*
    |--------------------------------------------------------------------------
    | Key Management
    |--------------------------------------------------------------------------
    */
    'keys' => [
        'rotation_interval_days' => env('PANETA_KEY_ROTATION_DAYS', 90),
        'deprecation_grace_days' => env('PANETA_KEY_DEPRECATION_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Versioning
    |--------------------------------------------------------------------------
    */
    'api' => [
        'current_version' => 'v1',
        'supported_versions' => ['v1'],
        'deprecated_versions' => [],
        'minimum_supported_months' => 12,
    ],

    /*
    |--------------------------------------------------------------------------
    | Fee Configuration
    |--------------------------------------------------------------------------
    */
    'fees' => [
        'platform' => env('PANETA_FEE_PLATFORM', 0.99),
        'cross_border' => env('PANETA_FEE_CROSS_BORDER', 1.49),
        'p2p_fx' => env('PANETA_FEE_P2P_FX', 0.50),
        'merchant' => env('PANETA_FEE_MERCHANT', 2.50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction Limits
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'daily_transaction_limit' => env('PANETA_DAILY_LIMIT', 10000),
        'single_transaction_limit' => env('PANETA_SINGLE_LIMIT', 5000),
        'cross_border_daily_limit' => env('PANETA_CROSS_BORDER_DAILY_LIMIT', 50000),
    ],

    /*
    |--------------------------------------------------------------------------
    | SLO Targets (Service Level Objectives)
    |--------------------------------------------------------------------------
    */
    'slo' => [
        'local_transaction_ms' => env('PANETA_SLO_LOCAL_TX_MS', 500),
        'cross_border_initiation_ms' => env('PANETA_SLO_CB_INIT_MS', 1000),
        'fx_quote_ms' => env('PANETA_SLO_FX_QUOTE_MS', 300),
        'api_availability_percent' => env('PANETA_SLO_AVAILABILITY', 99.9),
        'websocket_latency_ms' => env('PANETA_SLO_WS_LATENCY_MS', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alert Thresholds
    |--------------------------------------------------------------------------
    */
    'alerts' => [
        'error_rate_percent' => env('PANETA_ALERT_ERROR_RATE', 1.0),
        'queue_depth' => env('PANETA_ALERT_QUEUE_DEPTH', 1000),
        'success_rate_percent' => env('PANETA_ALERT_SUCCESS_RATE', 95.0),
        'db_connections_percent' => env('PANETA_ALERT_DB_CONN', 80.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Recovery Targets
    |--------------------------------------------------------------------------
    */
    'recovery' => [
        'rpo_minutes' => env('PANETA_RPO_MINUTES', 5),
        'rto_minutes' => env('PANETA_RTO_MINUTES', 30),
        'uptime_target_percent' => env('PANETA_UPTIME_TARGET', 99.9),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Retention (days, -1 = permanent)
    |--------------------------------------------------------------------------
    */
    'retention' => [
        'audit_logs' => env('PANETA_RETENTION_AUDIT', 2555),
        'security_logs' => env('PANETA_RETENTION_SECURITY', 1095),
        'transactions' => env('PANETA_RETENTION_TRANSACTIONS', -1),
        'login_attempts' => env('PANETA_RETENTION_LOGIN', 365),
        'notifications' => env('PANETA_RETENTION_NOTIFICATIONS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment Controls
    |--------------------------------------------------------------------------
    */
    'environment' => [
        'require_https' => env('PANETA_REQUIRE_HTTPS', true),
        'require_idempotency' => env('PANETA_REQUIRE_IDEMPOTENCY', true),
        'require_email_verification' => env('PANETA_REQUIRE_EMAIL_VERIFY', true),
        'debug_mode_allowed' => env('PANETA_DEBUG_ALLOWED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check Configuration
    |--------------------------------------------------------------------------
    */
    'health' => [
        'check_interval_seconds' => env('PANETA_HEALTH_INTERVAL', 30),
        'services' => [
            'database',
            'cache',
            'queue',
            'storage',
        ],
    ],
];
