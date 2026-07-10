<?php

declare(strict_types=1);

namespace Syriable\Metrics\Contracts;

use Syriable\Metrics\Support\Period;

/**
 * Decides which period a metric is compared against.
 *
 * Strategies only pick the reference window; the arithmetic (difference,
 * percentage, direction) is uniform and lives in the comparison engine.
 */
interface ComparisonStrategy
{
    public function key(): string;

    public function referencePeriod(Period $current): Period;
}
