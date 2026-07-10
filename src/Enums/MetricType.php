<?php

declare(strict_types=1);

namespace Syriable\Metrics\Enums;

/**
 * The shape of a metric result.
 *
 * - Value:     one number per dataset (optionally compared against a
 *              reference period).
 * - Trend:     a gap-filled time series per dataset.
 * - Partition: one number per group per dataset (group-by).
 */
enum MetricType: string
{
    case Value = 'value';
    case Trend = 'trend';
    case Partition = 'partition';
}
