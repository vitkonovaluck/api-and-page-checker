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

];
