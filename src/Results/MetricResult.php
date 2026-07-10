<?php

declare(strict_types=1);

namespace Syriable\Metrics\Results;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Syriable\Metrics\Contracts\Serializer;
use Syriable\Metrics\Enums\Interval;
use Syriable\Metrics\Enums\MetricType;
use Syriable\Metrics\Support\Comparison;
use Syriable\Metrics\Support\Period;

/**
 * The complete, immutable output of one metric execution.
 *
 * Everything is computed by the time this object exists; toArray() only
 * projects, through whatever Serializer the manager was configured with.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class MetricResult implements Arrayable, JsonSerializable
{
    /**
     * @param  list<DatasetResult>  $datasets
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $key,
        public MetricType $type,
        public ?string $rangeKey,
        public ?Period $period,
        public ?Interval $interval,
        public string $timezone,
        public array $datasets,
        public CarbonImmutable $generatedAt,
        public bool $fromCache,
        public array $meta,
        private Serializer $serializer,
    ) {}

    /**
     * The value of a dataset (the first one by default) — the common case
     * for single-number metrics.
     */
    public function value(?string $dataset = null): int|float|null
    {
        return $this->dataset($dataset)?->value;
    }

    /**
     * The trend points of a dataset (the first one by default).
     *
     * @return list<TrendPoint>|null
     */
    public function points(?string $dataset = null): ?array
    {
        return $this->dataset($dataset)?->points;
    }

    /**
     * The partition groups of a dataset (the first one by default).
     *
     * @return list<PartitionGroup>|null
     */
    public function groups(?string $dataset = null): ?array
    {
        return $this->dataset($dataset)?->groups;
    }

    public function comparison(?string $dataset = null): ?Comparison
    {
        return $this->dataset($dataset)?->comparison;
    }

    public function dataset(?string $name = null): ?DatasetResult
    {
        if ($name === null) {
            return $this->datasets[0] ?? null;
        }

        foreach ($this->datasets as $dataset) {
            if ($dataset->name === $name) {
                return $dataset;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->serializer->serialize($this);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
