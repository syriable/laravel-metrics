<?php

declare(strict_types=1);

namespace Syriable\Metrics\Aggregates;

use Syriable\Metrics\Contracts\Aggregate;

final class CountDistinct implements Aggregate
{
    public function key(): string
    {
        return 'count_distinct';
    }

    public function expression(string $inner): string
    {
        return "count(distinct {$inner})";
    }

    public function requiresColumn(): bool
    {
        return true;
    }

    public function emptyValue(): int
    {
        return 0;
    }
}
