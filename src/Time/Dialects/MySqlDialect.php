<?php

declare(strict_types=1);

namespace Syriable\Metrics\Time\Dialects;

use Syriable\Metrics\Contracts\DateDialect;
use Syriable\Metrics\Enums\Interval;

final class MySqlDialect implements DateDialect
{
    public function drivers(): array
    {
        return ['mysql', 'mariadb'];
    }

    public function bucketExpression(string $wrappedColumn, Interval $interval, int $offsetMinutes): string
    {
        $column = $offsetMinutes === 0
            ? $wrappedColumn
            : "({$wrappedColumn} + INTERVAL {$offsetMinutes} MINUTE)";

        return match ($interval) {
            Interval::Minute => "date_format({$column}, '%Y-%m-%d %H:%i')",
            Interval::Hour => "date_format({$column}, '%Y-%m-%d %H:00')",
            Interval::Day => "date_format({$column}, '%Y-%m-%d')",
            Interval::Week => "date_format({$column}, '%x-W%v')",
            Interval::Month => "date_format({$column}, '%Y-%m')",
            Interval::Quarter => "concat(date_format({$column}, '%Y'), '-Q', quarter({$column}))",
            Interval::Year => "date_format({$column}, '%Y')",
        };
    }
}
