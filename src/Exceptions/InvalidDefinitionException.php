<?php

declare(strict_types=1);

namespace Syriable\Metrics\Exceptions;

use DateTimeInterface;

final class InvalidDefinitionException extends MetricsException
{
    public static function periodStartAfterEnd(DateTimeInterface $start, DateTimeInterface $end): self
    {
        return new self(sprintf(
            'Period start [%s] must not be after its end [%s].',
            $start->format(DateTimeInterface::ATOM),
            $end->format(DateTimeInterface::ATOM),
        ));
    }

    public static function missingGroupBy(): self
    {
        return new self('A partition metric requires a groupBy() column.');
    }

    public static function missingColumn(string $aggregate): self
    {
        return new self(sprintf('The [%s] aggregate requires a column.', $aggregate));
    }

    public static function comparisonRequiresPeriod(): self
    {
        return new self('Comparisons require a bounded range; an all-time metric has no reference period.');
    }

    public static function trendRequiresPeriod(): self
    {
        return new self('Trends require a bounded range; pick a range or an explicit period.');
    }

    public static function duplicateDataset(string $name): self
    {
        return new self(sprintf('A dataset or formula named [%s] is already defined on this metric.', $name));
    }

    public static function tooManyBuckets(int $count, int $max): self
    {
        return new self(sprintf(
            'The requested range and interval produce %d buckets (max %d). Use a coarser interval or a shorter range, or raise metrics.max_buckets.',
            $count,
            $max,
        ));
    }

    public static function unknownMetric(string $key): self
    {
        return new self(sprintf('No metric registered under [%s]. Register it with Metrics::register().', $key));
    }
}
