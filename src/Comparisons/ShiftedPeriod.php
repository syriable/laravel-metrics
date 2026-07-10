<?php

declare(strict_types=1);

namespace Syriable\Metrics\Comparisons;

use Syriable\Metrics\Contracts\ComparisonStrategy;
use Syriable\Metrics\Enums\Interval;
use Syriable\Metrics\Support\Period;

/**
 * Compare against the same window shifted back by whole calendar units —
 * "same period last week/month/quarter/year". Month-length edge cases are
 * handled without overflow (Mar 31 shifts to Feb 28/29, not Mar 3).
 */
final readonly class ShiftedPeriod implements ComparisonStrategy
{
    public function __construct(
        private string $key,
        private Interval $unit,
        private int $steps = 1,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function referencePeriod(Period $current): Period
    {
        return $current->shiftBackward($this->unit, $this->steps);
    }
}
