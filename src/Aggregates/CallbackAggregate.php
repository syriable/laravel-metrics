<?php

declare(strict_types=1);

namespace Syriable\Metrics\Aggregates;

use Closure;
use Syriable\Metrics\Contracts\Aggregate;

/**
 * Ad-hoc aggregate built from a closure, so simple custom aggregations
 * (median, stddev, percentile on databases that support it) don't require
 * a dedicated class:
 *
 *     Metrics::registerAggregate(new CallbackAggregate(
 *         'stddev', fn (string $inner) => "stddev({$inner})",
 *     ));
 */
final readonly class CallbackAggregate implements Aggregate
{
    /**
     * @param  Closure(string): string  $expression  receives the wrapped inner expression
     */
    public function __construct(
        private string $key,
        private Closure $expression,
        private bool $requiresColumn = true,
        private int|float|null $emptyValue = null,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function expression(string $inner): string
    {
        return ($this->expression)($inner);
    }

    public function requiresColumn(): bool
    {
        return $this->requiresColumn;
    }

    public function emptyValue(): int|float|null
    {
        return $this->emptyValue;
    }
}
