<?php

declare(strict_types=1);

namespace Syriable\Metrics\Results;

use Syriable\Metrics\Support\Comparison;

/**
 * The computed output of one dataset (a real aggregate or a formula).
 *
 * Exactly one of the shape fields is populated, matching the metric type:
 * value (Value), points (Trend), groups (Partition). Trends also carry the
 * series total so consumers don't re-sum client-side.
 */
final readonly class DatasetResult
{
    /**
     * @param  list<TrendPoint>|null  $points
     * @param  list<PartitionGroup>|null  $groups
     */
    public function __construct(
        public string $name,
        public int|float|null $value = null,
        public ?array $points = null,
        public ?array $groups = null,
        public ?Comparison $comparison = null,
        public bool $formula = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'name' => $this->name,
            'formula' => $this->formula,
        ];

        if ($this->points !== null) {
            $payload['points'] = array_map(static fn (TrendPoint $point) => $point->toArray(), $this->points);
            $payload['total'] = $this->value;
        } elseif ($this->groups !== null) {
            $payload['groups'] = array_map(static fn (PartitionGroup $group) => $group->toArray(), $this->groups);
            $payload['total'] = $this->value;
        } else {
            $payload['value'] = $this->value;
        }

        if ($this->comparison !== null) {
            $payload['comparison'] = $this->comparison->toArray();
        }

        return $payload;
    }
}
