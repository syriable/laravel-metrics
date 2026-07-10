<?php

declare(strict_types=1);

namespace Syriable\Metrics\Contracts;

/**
 * A database-side aggregation.
 *
 * Aggregates never touch the query builder and never see user input; they
 * receive an already-grammar-wrapped inner expression (a column, "*", or a
 * CASE expression for windowed single-query comparisons) and return the SQL
 * aggregate around it. Register new implementations with
 * Metrics::registerAggregate() — the engine core never needs to change.
 */
interface Aggregate
{
    /**
     * The name the aggregate is referenced by, e.g. "count", "sum".
     */
    public function key(): string;

    /**
     * Build the SQL aggregate expression around the given inner expression.
     *
     * @param  string  $inner  a grammar-wrapped column, "*", or a CASE expression
     */
    public function expression(string $inner): string;

    /**
     * Whether the aggregate needs a column (count does not).
     */
    public function requiresColumn(): bool;

    /**
     * The value representing "no rows" (0 for count/sum, null for avg/min/max).
     */
    public function emptyValue(): int|float|null;
}
