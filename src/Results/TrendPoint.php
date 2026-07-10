<?php

declare(strict_types=1);

namespace Syriable\Metrics\Results;

use Carbon\CarbonImmutable;

/**
 * One bucket of a trend series. The key is the canonical machine key the
 * engine joined on; the label is derived presentation.
 */
final readonly class TrendPoint
{
    public function __construct(
        public string $key,
        public string $label,
        public CarbonImmutable $start,
        public int|float|null $value,
    ) {}

    /**
     * @return array{key: string, label: string, start: string, value: int|float|null}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'start' => $this->start->toIso8601String(),
            'value' => $this->value,
        ];
    }
}
