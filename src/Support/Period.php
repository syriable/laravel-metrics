<?php

declare(strict_types=1);

namespace Syriable\Metrics\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Syriable\Metrics\Enums\Interval;
use Syriable\Metrics\Exceptions\InvalidDefinitionException;

/**
 * An immutable, end-inclusive window of time.
 *
 * A Period may carry a calendar unit hint. Calendar-aligned ranges
 * (month-to-date, quarter-to-date, …) set it so that previous() performs a
 * like-for-like "elapsed portion" comparison — July 1–10 compares against
 * June 1–10, not against the 10 days ending June 30. Rolling ranges leave
 * it null and previous() shifts by exact duration instead.
 */
final readonly class Period
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public ?Interval $calendarUnit = null,
    ) {
        if ($start->greaterThan($end)) {
            throw InvalidDefinitionException::periodStartAfterEnd($start, $end);
        }
    }

    public static function between(
        DateTimeInterface|string $start,
        DateTimeInterface|string $end,
        ?string $timezone = null,
    ): self {
        return new self(
            CarbonImmutable::parse($start, $timezone),
            CarbonImmutable::parse($end, $timezone),
        );
    }

    public function timezone(): string
    {
        return $this->start->timezoneName;
    }

    public function lengthInSeconds(): int
    {
        return (int) abs($this->start->diffInSeconds($this->end));
    }

    /**
     * The immediately preceding period.
     *
     * Calendar-aligned periods shift back one calendar unit (without month
     * overflow) so both bounds land on the equivalent elapsed portion.
     * Duration-based periods slide back by their exact length, ending one
     * second before the current period starts.
     */
    public function previous(): self
    {
        if ($this->calendarUnit !== null) {
            return $this->shiftBackward($this->calendarUnit, 1);
        }

        $length = $this->lengthInSeconds();

        return new self(
            $this->start->subSeconds($length + 1),
            $this->start->subSecond(),
        );
    }

    /**
     * Shift both bounds back by whole calendar units (without overflow),
     * e.g. "same period last year".
     */
    public function shiftBackward(Interval $unit, int $steps = 1): self
    {
        $shift = fn (CarbonImmutable $date): CarbonImmutable => match ($unit) {
            Interval::Minute => $date->subMinutes($steps),
            Interval::Hour => $date->subHours($steps),
            Interval::Day => $date->subDays($steps),
            Interval::Week => $date->subWeeks($steps),
            Interval::Month => $date->subMonthsNoOverflow($steps),
            Interval::Quarter => $date->subMonthsNoOverflow(3 * $steps),
            Interval::Year => $date->subMonthsNoOverflow(12 * $steps),
        };

        return new self($shift($this->start), $shift($this->end), $this->calendarUnit);
    }

    /**
     * @return array{start: string, end: string, timezone: string}
     */
    public function toArray(): array
    {
        return [
            'start' => $this->start->toIso8601String(),
            'end' => $this->end->toIso8601String(),
            'timezone' => $this->timezone(),
        ];
    }
}
