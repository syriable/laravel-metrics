<?php

declare(strict_types=1);

namespace Syriable\Metrics\Blueprints;

use Syriable\Metrics\Contracts\MetricBlueprint;

/**
 * The plain metric scaffold generated when no shape option is given.
 */
final readonly class DefaultMetricBlueprint implements MetricBlueprint
{
    public function key(): string
    {
        return 'default';
    }

    public function option(): ?string
    {
        return null;
    }

    public function description(): string
    {
        return 'A blank metric definition';
    }

    public function stubPath(): string
    {
        return dirname(__DIR__, 2).'/stubs/metric.stub';
    }
}
