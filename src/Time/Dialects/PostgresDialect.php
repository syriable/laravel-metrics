<?php

declare(strict_types=1);

namespace Syriable\Metrics\Time\Dialects;

use Syriable\Metrics\Contracts\DateDialect;
use Syriable\Metrics\Enums\Interval;

final class PostgresDialect implements DateDialect
{
    public function drivers(): array
    {
        return ['pgsql'];
    }

    public function bucketExpression(string $wrappedColumn, Interval $interval, int $offsetMinutes): string
    {
        $column = $offsetMinutes === 0
            ? $wrappedColumn
            : "({$wrappedColumn} + interval '{$offsetMinutes} minutes')";

        return match ($interval) {
            Interval::Minute => "to_char({$column}, 'YYYY-MM-DD HH24:MI')",
            Interval::Hour => "to_char({$column}, 'YYYY-MM-DD HH24:00')",
            Interval::Day => "to_char({$column}, 'YYYY-MM-DD')",
            Interval::Week => "to_char({$column}, 'IYYY-\"W\"IW')",
            Interval::Month => "to_char({$column}, 'YYYY-MM')",
            Interval::Quarter => "to_char({$column}, 'YYYY-\"Q\"Q')",
            Interval::Year => "to_char({$column}, 'YYYY')",
        };
    }
}
