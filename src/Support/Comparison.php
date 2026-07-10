<?php

declare(strict_types=1);

namespace Syriable\Metrics\Support;

use Syriable\Metrics\Enums\Direction;

/**
 * The outcome of comparing a value against a reference period.
 *
 * All derived numbers (difference, percentage, direction) are computed once,
 * server-side, at construction — consumers never re-derive them.
 */
final readonly class Comparison
{
    public ?float $difference;

    public ?float $percentage;

    public ?Direction $direction;

    public function __construct(
        public string $strategy,
        public Period $period,
        public ?float $current,
        public ?float $previous,
        int $precision = 2,
        int $roundingMode = PHP_ROUND_HALF_UP,
    ) {
        if ($current === null || $previous === null) {
            $this->difference = null;
            $this->percentage = null;
            $this->direction = null;

            return;
        }

        $difference = round($current - $previous, $precision, $roundingMode);

        $this->difference = $difference;
        $this->percentage = $previous == 0.0
            ? ($current == 0.0 ? 0.0 : null)
            : round(($current - $previous) / abs($previous) * 100, $precision, $roundingMode);
        $this->direction = Direction::of($difference);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'strategy' => $this->strategy,
            'period' => $this->period->toArray(),
            'previous' => $this->previous,
            'difference' => $this->difference,
            'percentage' => $this->percentage,
            'direction' => $this->direction?->value,
        ];
    }
}
