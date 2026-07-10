<?php

declare(strict_types=1);

namespace Syriable\Metrics\Aggregates;

use Syriable\Metrics\Contracts\Aggregate;

final class Sum implements Aggregate
{
    public function key(): string
    {
        return 'sum';
    }

    public function expression(string $inner): string
    {
        return "sum({$inner})";
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
