<?php

declare(strict_types=1);

namespace Syriable\Metrics\Time\Dialects;

use Syriable\Metrics\Contracts\DateDialect;
use Syriable\Metrics\Enums\Interval;

final class SqlServerDialect implements DateDialect
{
    public function drivers(): array
    {
        return ['sqlsrv'];
    }

    public function bucketExpression(string $wrappedColumn, Interval $interval, int $offsetMinutes): string
    {
        $column = $offsetMinutes === 0
            ? $wrappedColumn
            : "DATEADD(minute, {$offsetMinutes}, {$wrappedColumn})";

        return match ($interval) {
            Interval::Minute => "FORMAT({$column}, 'yyyy-MM-dd HH:mm')",
            Interval::Hour => "FORMAT({$column}, 'yyyy-MM-dd HH:00')",
            Interval::Day => "FORMAT({$column}, 'yyyy-MM-dd')",
            // T-SQL has DATEPART(iso_week, …) but no ISO week-year; shifting
            // the date to the Thursday of its ISO week makes YEAR() correct
            // at year boundaries.
            Interval::Week => "CONCAT(YEAR(DATEADD(day, 26 - DATEPART(iso_week, {$column}), {$column})), '-W', RIGHT(CONCAT('0', DATEPART(iso_week, {$column})), 2))",
            Interval::Month => "FORMAT({$column}, 'yyyy-MM')",
            Interval::Quarter => "CONCAT(YEAR({$column}), '-Q', DATEPART(quarter, {$column}))",
            Interval::Year => "CAST(YEAR({$column}) AS varchar(4))",
        };
    }
}
