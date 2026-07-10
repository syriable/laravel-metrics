<?php

declare(strict_types=1);

namespace Syriable\Metrics\Comparisons;

use Syriable\Metrics\Contracts\ComparisonStrategy;
use Syriable\Metrics\Support\Period;

/**
 * Compare against the immediately preceding period. Delegates to
 * Period::previous(), which understands calendar-aligned "elapsed portion"
 * semantics (July 1–10 vs June 1–10 for MTD) as well as rolling windows.
 */
final class PreviousPeriod implements ComparisonStrategy
{
    public function key(): string
    {
        return 'previous_period';
    }

    public function referencePeriod(Period $current): Period
    {
        return $current->previous();
    }
}
