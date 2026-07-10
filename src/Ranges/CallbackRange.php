<?php

declare(strict_types=1);

namespace Syriable\Metrics\Ranges;

use Carbon\CarbonImmutable;
use Closure;
use Syriable\Metrics\Contracts\Range;
use Syriable\Metrics\Support\Period;

/**
 * A named range defined by a closure. All built-in ranges are instances of
 * this class; custom fiscal calendars register the same way:
 *
 *     Metrics::registerRange(new CallbackRange('fiscal_ytd', 'Fiscal YTD',
 *         fn (CarbonImmutable $now) => new Period($fiscalStart, $now),
 *     ));
 */
final readonly class CallbackRange implements Range
{
    /**
     * @param  Closure(CarbonImmutable): ?Period  $resolver  null result = all time
     */
    public function __construct(
        private string $key,
        private string $label,
        private Closure $resolver,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function period(CarbonImmutable $now): ?Period
    {
        return ($this->resolver)($now);
    }
}
