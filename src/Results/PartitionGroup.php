<?php

declare(strict_types=1);

namespace Syriable\Metrics\Results;

/**
 * One group of a partition dataset. The percentage is computed by the
 * engine against the dataset total — serialization never does math.
 */
final readonly class PartitionGroup
{
    public function __construct(
        public string|int|bool|null $key,
        public int|float|null $value,
        public ?float $percentage,
    ) {}

    /**
     * @return array{key: string|int|bool|null, value: int|float|null, percentage: float|null}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
            'percentage' => $this->percentage,
        ];
    }
}
