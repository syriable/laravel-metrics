<?php

declare(strict_types=1);

namespace Syriable\Metrics\Aggregates;

use Syriable\Metrics\Contracts\Aggregate;

final class Minimum implements Aggregate
{
    public function key(): string
    {
        return 'min';
    }

    public function expression(string $inner): string
    {
        return "min({$inner})";
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
