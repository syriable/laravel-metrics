<?php

declare(strict_types=1);

namespace Syriable\Metrics\Contracts;

use Carbon\CarbonImmutable;
use Syriable\Metrics\Support\Period;

/**
 * A named time range ("today", "mtd", "30d", …).
 *
 * Ranges are pure resolvers: given "now" (always injected, never read from
 * the clock, so range math is deterministic and testable) they produce an
 * immutable Period — or null, meaning unbounded (all time).
 */
interface Range
{
    public function key(): string;

    public function label(): string;

    public function period(CarbonImmutable $now): ?Period;
}
