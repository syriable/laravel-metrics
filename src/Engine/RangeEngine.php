<?php

declare(strict_types=1);

namespace Syriable\Metrics\Engine;

use Carbon\CarbonImmutable;
use Syriable\Metrics\Contracts\Range;
use Syriable\Metrics\Metrics;
use Syriable\Metrics\Support\Period;

/**
 * Turns whatever the builder was given — a registered key, a rolling
 * pattern, a Range object, an explicit Period, or nothing — into the
 * concrete [rangeKey, Period] pair the rest of the engine consumes.
 */
final readonly class RangeEngine
{
    public function __construct(private Metrics $manager) {}

    /**
     * @return array{0: ?string, 1: ?Period}
     */
    public function resolve(
        Range|Period|string|null $input,
        bool $allTime,
        CarbonImmutable $now,
        ?string $default,
    ): array {
        if ($allTime) {
            return ['all', null];
        }

        $input ??= $default;

        if ($input === null || $input === 'all') {
            return ['all', null];
        }

        if ($input instanceof Period) {
            return [null, $input];
        }

        $range = is_string($input) ? $this->manager->range($input) : $input;

        return [$range->key(), $range->period($now)];
    }
}
