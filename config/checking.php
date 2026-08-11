<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Outbound check rate limit
    |--------------------------------------------------------------------------
    |
    | Maximum number of HTTP check requests per host per minute. Requests are
    | spaced evenly (≈60s / limit). Set to 0 to disable throttling.
    |
    */

    'requests_per_minute' => (int) env('CHECK_REQUESTS_PER_MINUTE', 32),

];
