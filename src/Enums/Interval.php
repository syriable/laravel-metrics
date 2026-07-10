<?php

declare(strict_types=1);

namespace Syriable\Metrics\Enums;

use Carbon\CarbonImmutable;

/**
 * The time buckets a trend can be grouped by.
 *
 * An interval owns everything about its bucket: how to truncate a date to
 * the bucket start, how to advance to the next bucket, and how to render
 * the canonical machine key. Canonical keys — not human labels — are the
 * join keys between PHP-generated timelines and SQL-generated rows, so the
 * formats here must stay in lockstep with the date dialect expressions.
 */
enum Interval: string
{
    case Minute = 'minute';
    case Hour = 'hour';
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Quarter = 'quarter';
    case Year = 'year';

    /**
     * Truncate the given date to the start of its bucket.
     */
    public function truncate(CarbonImmutable $date): CarbonImmutable
    {
        return match ($this) {
            self::Minute => $date->startOfMinute(),
            self::Hour => $date->startOfHour(),
            self::Day => $date->startOfDay(),
            self::Week => $date->startOfWeek(),
            self::Month => $date->startOfMonth(),
            self::Quarter => $date->startOfQuarter(),
            self::Year => $date->startOfYear(),
        };
    }

    /**
     * Advance a bucket-start date to the start of the next bucket.
     */
    public function advance(CarbonImmutable $date, int $steps = 1): CarbonImmutable
    {
        return match ($this) {
            self::Minute => $date->addMinutes($steps),
            self::Hour => $date->addHours($steps),
            self::Day => $date->addDays($steps),
            self::Week => $date->addWeeks($steps),
            self::Month => $date->addMonths($steps),
            self::Quarter => $date->addMonths(3 * $steps),
            self::Year => $date->addYears($steps),
        };
    }

    /**
     * The canonical machine key for the bucket containing the given date.
     *
     * These formats are mirrored, character for character, by every
     * date dialect's SQL expression.
     */
    public function key(CarbonImmutable $date): string
    {
        return match ($this) {
            self::Minute => $date->format('Y-m-d H:i'),
            self::Hour => $date->format('Y-m-d H:00'),
            self::Day => $date->format('Y-m-d'),
            self::Week => sprintf('%d-W%02d', $date->isoWeekYear, $date->isoWeek),
            self::Month => $date->format('Y-m'),
            self::Quarter => sprintf('%s-Q%d', $date->format('Y'), $date->quarter),
            self::Year => $date->format('Y'),
        };
    }

    /**
     * A human-readable label for the bucket containing the given date.
     *
     * Labels are presentation-only; they are generated after gap-filling
     * and never used to join data.
     */
    public function label(CarbonImmutable $date): string
    {
        return match ($this) {
            self::Minute => $date->format('M j, Y H:i'),
            self::Hour => $date->format('M j, Y H:00'),
            self::Day => $date->format('M j, Y'),
            self::Week => $date->startOfWeek()->format('M j').' – '.$date->endOfWeek()->format('M j, Y'),
            self::Month => $date->format('F Y'),
            self::Quarter => sprintf('Q%d %s', $date->quarter, $date->format('Y')),
            self::Year => $date->format('Y'),
        };
    }
}
