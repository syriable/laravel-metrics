<?php

declare(strict_types=1);

namespace Syriable\Metrics\Aggregates;

use Syriable\Metrics\Contracts\Aggregate;

final class Maximum implements Aggregate
{
    public function key(): string
    {
        return 'max';
    }

    public function expression(string $inner): string
    {
        return "max({$inner})";
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
