<?php

declare(strict_types=1);

namespace Syriable\Metrics\Aggregates;

use Syriable\Metrics\Contracts\Aggregate;

final class Count implements Aggregate
{
    public function key(): string
    {
        return 'count';
    }

    public function expression(string $inner): string
    {
        return "count({$inner})";
    }

    public function requiresColumn(): bool
    {
        return false;
    }

    public function emptyValue(): int
    {
        return 0;
    }
}
