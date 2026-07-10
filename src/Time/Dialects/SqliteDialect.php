<?php

declare(strict_types=1);

namespace Syriable\Metrics\Time\Dialects;

use Syriable\Metrics\Contracts\DateDialect;
use Syriable\Metrics\Enums\Interval;

final class SqliteDialect implements DateDialect
{
    public function drivers(): array
    {
        return ['sqlite'];
    }

    public function bucketExpression(string $wrappedColumn, Interval $interval, int $offsetMinutes): string
    {
        $column = $offsetMinutes === 0
            ? $wrappedColumn
            : "datetime({$wrappedColumn}, '".($offsetMinutes > 0 ? '+' : '')."{$offsetMinutes} minutes')";

        return match ($interval) {
            Interval::Minute => "strftime('%Y-%m-%d %H:%M', {$column})",
            Interval::Hour => "strftime('%Y-%m-%d %H:00', {$column})",
            Interval::Day => "strftime('%Y-%m-%d', {$column})",
            Interval::Week => $this->isoWeekExpression($column),
            Interval::Month => "strftime('%Y-%m', {$column})",
            Interval::Quarter => "(strftime('%Y', {$column}) || '-Q' || cast((cast(strftime('%m', {$column}) as integer) + 2) / 3 as text))",
            Interval::Year => "strftime('%Y', {$column})",
        };
    }

    /**
     * ISO year-week without strftime's %G/%V (SQLite ≥ 3.46 only): the
     * Thursday of a date's week always lies in the ISO week-year, so
     * shifting to it makes both the year and the week number trivial.
     */
    private function isoWeekExpression(string $column): string
    {
        $thursday = "date({$column}, '-3 days', 'weekday 4')";

        return "(strftime('%Y', {$thursday}) || '-W' || substr('00' || cast((strftime('%j', {$thursday}) - 1) / 7 + 1 as text), -2))";
    }
}
