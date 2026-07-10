<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Display timezone
    |--------------------------------------------------------------------------
    | The timezone range boundaries and trend buckets are computed in.
    | null falls back to app.timezone. Overridable per metric with
    | ->timezone().
    */
    'timezone' => null,

    /*
    |--------------------------------------------------------------------------
    | Storage timezone
    |--------------------------------------------------------------------------
    | The timezone your datetime columns are stored in. null falls back to
    | app.timezone (Laravel's default behavior for timestamps).
    */
    'storage_timezone' => null,

    /*
    |--------------------------------------------------------------------------
    | Default range
    |--------------------------------------------------------------------------
    | Used when a value/trend metric doesn't declare a range. Any registered
    | range key or rolling pattern ("30d", "12mo", "24h", …), or "all".
    */
    'default_range' => '30d',

    /*
    |--------------------------------------------------------------------------
    | Rounding
    |--------------------------------------------------------------------------
    | Applied to every computed number. Overridable per metric with
    | ->precision().
    */
    'precision' => [
        'digits' => 2,
        'mode' => PHP_ROUND_HALF_UP,
    ],

    /*
    |--------------------------------------------------------------------------
    | Bucket cap
    |--------------------------------------------------------------------------
    | A trend may not produce more buckets than this (guards against
    | minute-interval-over-a-year style requests).
    */
    'max_buckets' => 5000,

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    | Off by default. Set "ttl" (seconds) to cache every metric, or leave
    | null and opt in per metric with ->cache($ttl). Keys are derived from
    | the metric's compiled SQL + execution context, so definition changes
    | invalidate automatically.
    */
    'cache' => [
        'store' => null,
        'prefix' => 'metrics',
        'ttl' => null,
    ],

];
