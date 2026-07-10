<?php

declare(strict_types=1);

namespace Syriable\Metrics\Comparisons;

use Closure;
use Syriable\Metrics\Contracts\ComparisonStrategy;
use Syriable\Metrics\Support\Period;

/**
 * A comparison strategy defined by a closure, for one-off reference
 * windows (e.g. "vs the launch month").
 */
final readonly class CallbackComparison implements ComparisonStrategy
{
    /**
     * @param  Closure(Period): Period  $resolver
     */
    public function __construct(
        private string $key,
        private Closure $resolver,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function referencePeriod(Period $current): Period
    {
        return ($this->resolver)($current);
    }
}
