<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Delay between scheduled address checks (seconds)
    |--------------------------------------------------------------------------
    |
    | After each queued check finishes, the worker waits this long before the
    | next job can proceed. Combined with max_per_minute this keeps load around
    | 28–30 checks per minute when HTTP responses are fast.
    |
    */
    'delay_seconds' => (int) env('CHECKING_DELAY_SECONDS', 1),
    /*
    |--------------------------------------------------------------------------
    | Max scheduled checks per minute
    |--------------------------------------------------------------------------
    */
    'max_per_minute' => (int) env('CHECKING_MAX_PER_MINUTE', 30),
    /*
    |--------------------------------------------------------------------------
    | Outbound check rate limit
    |--------------------------------------------------------------------------
    |
    | Maximum number of HTTP check requests per host per minute. Requests are
    | spaced evenly (≈60s / limit). Set to 0 to disable throttling.
    | A per-site value in site settings overrides this default.
    |
    */
    'requests_per_minute' => (int) env('CHECK_REQUESTS_PER_MINUTE', 32),
    /*
    |--------------------------------------------------------------------------
    | Chain the next site check after the previous run finishes
    |--------------------------------------------------------------------------
    |
    | When a site schedule interval is "after", the last address job of a run
    | queues another check of schedule-enabled addresses after this delay.
    |
    */
    'chain_delay_seconds' => (int) env('CHECKING_CHAIN_DELAY_SECONDS', 60),
    /*
    |--------------------------------------------------------------------------
    | Retry after HTTP 429 Too Many Requests
    |--------------------------------------------------------------------------
    |
    | Pages that fan out into several backend APIs often trip the target's
    | limiter even when a single endpoint check is fine. Retry the same check
    | before saving a snapshot. 1 = do not retry.
    |
    */
    'too_many_requests_retries' => (int) env('CHECK_429_RETRIES', 3),
    'too_many_requests_backoff_ms' => (int) env('CHECK_429_BACKOFF_MS', 2000),
    'too_many_requests_max_wait_ms' => (int) env('CHECK_429_MAX_WAIT_MS', 10000),
    /*
    |--------------------------------------------------------------------------
    | Per-site check queues
    |--------------------------------------------------------------------------
    |
    | Each site is dispatched onto "{prefix}-{id}" and sites:queue-work starts
    | one worker process per site so checks run in parallel across sites.
    |
    */
    'queue_prefix' => env('CHECK_QUEUE_PREFIX', 'site'),
    'worker_scan_seconds' => (int) env('CHECK_WORKER_SCAN_SECONDS', 5),
    /*
    |--------------------------------------------------------------------------
    | Portable site transfer (JSON import/export)
    |--------------------------------------------------------------------------
    |
    | Used to move site configuration and addresses between servers without
    | replacing the whole database. Check history (snapshots) is not included.
    |
    */
    'transfer' => [
        'format' => 'api-checker-sites',
        'version' => 1,
        'max_upload_kb' => (int) env('SITE_TRANSFER_MAX_UPLOAD_KB', 10240),
    ],
    /*
    |--------------------------------------------------------------------------
    | Agent snapshot body size
    |--------------------------------------------------------------------------
    |
    | Maximum request body the agent API will accept when ingesting a snapshot.
    |
    */
    'agent_snapshot_body_max_kb' => (int) env('AGENT_SNAPSHOT_BODY_MAX_KB', 1024),
];
