<?php

declare(strict_types=1);

namespace Syriable\Metrics\Time;

use Carbon\CarbonImmutable;
use Generator;
use Syriable\Metrics\Enums\Interval;
use Syriable\Metrics\Exceptions\InvalidDefinitionException;
use Syriable\Metrics\Support\Period;

/**
 * Lazily generates the bucket sequence for a period and interval.
 *
 * The generator yields canonical key => bucket start, and is the single
 * source of truth for gap-filling: every key the SQL side can produce for
 * rows inside the period is generated here, so merging is a pure array
 * union on machine keys. The bucket cap keeps a careless minute×year
 * request from allocating unbounded memory.
 */
final readonly class Timeline
{
    public function __construct(
        public Period $period,
        public Interval $interval,
        private int $maxBuckets = 5000,
    ) {}

    /**
     * @return Generator<string, CarbonImmutable>
     */
    public function buckets(): Generator
    {
        $cursor = $this->interval->truncate($this->period->start);
        $count = 0;

        while ($cursor->lessThanOrEqualTo($this->period->end)) {
            if (++$count > $this->maxBuckets) {
                throw InvalidDefinitionException::tooManyBuckets($count, $this->maxBuckets);
            }

            yield $this->interval->key($cursor) => $cursor;

            $cursor = $this->interval->advance($cursor);
        }
    }
}
