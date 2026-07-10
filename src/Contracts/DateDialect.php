<?php

declare(strict_types=1);

namespace Syriable\Metrics\Contracts;

use Syriable\Metrics\Enums\Interval;

/**
 * Database-specific date bucketing.
 *
 * A dialect turns a datetime column into the canonical bucket key for an
 * interval — the exact same string Interval::key() produces in PHP, so
 * gap-filling joins on machine keys instead of formatted labels. All
 * driver-specific SQL in the package lives behind this contract; supporting
 * a new database means registering one class.
 */
interface DateDialect
{
    /**
     * The Laravel connection driver names this dialect serves.
     *
     * @return list<string>
     */
    public function drivers(): array;

    /**
     * SQL expression producing the canonical bucket key for the interval.
     *
     * @param  string  $wrappedColumn  the date column, already grammar-wrapped
     * @param  int  $offsetMinutes  minutes to add before bucketing (display
     *                              timezone minus storage timezone; minute
     *                              precision so half-hour offsets work)
     */
    public function bucketExpression(string $wrappedColumn, Interval $interval, int $offsetMinutes): string;
}
