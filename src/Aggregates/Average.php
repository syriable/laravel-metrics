<?php

declare(strict_types=1);

namespace Syriable\Metrics\Aggregates;

use Syriable\Metrics\Contracts\Aggregate;

final class Average implements Aggregate
{
    public function key(): string
    {
        return 'avg';
    }

    public function expression(string $inner): string
    {
        return "avg({$inner})";
    }

    public function requiresColumn(): bool
    {
        return true;
    }

    public function emptyValue(): null
    {
        return null;
    }
}
