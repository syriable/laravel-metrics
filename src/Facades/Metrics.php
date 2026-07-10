<?php

declare(strict_types=1);

namespace Syriable\Metrics\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Syriable\Metrics\Builder\MetricBuilder query(\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|string|\Closure $source)
 * @method static \Syriable\Metrics\Metrics register(string|\Syriable\Metrics\Metric ...$metrics)
 * @method static \Syriable\Metrics\Results\MetricResult run(string $key, array $context = [])
 * @method static array<string, \Syriable\Metrics\Metric> registered()
 * @method static \Syriable\Metrics\Metrics registerAggregate(\Syriable\Metrics\Contracts\Aggregate $aggregate)
 * @method static \Syriable\Metrics\Metrics registerDialect(\Syriable\Metrics\Contracts\DateDialect $dialect)
 * @method static \Syriable\Metrics\Metrics registerRange(\Syriable\Metrics\Contracts\Range $range)
 * @method static \Syriable\Metrics\Metrics registerComparison(\Syriable\Metrics\Contracts\ComparisonStrategy $strategy)
 * @method static \Syriable\Metrics\Metrics useSerializer(\Syriable\Metrics\Contracts\Serializer $serializer)
 * @method static \Syriable\Metrics\Metrics useFormulaEvaluator(\Syriable\Metrics\Contracts\FormulaEvaluator $evaluator)
 * @method static \Syriable\Metrics\Contracts\Aggregate aggregate(string $key)
 * @method static \Syriable\Metrics\Contracts\DateDialect dialect(string $driver)
 * @method static \Syriable\Metrics\Contracts\Range range(string $key)
 * @method static \Syriable\Metrics\Contracts\ComparisonStrategy comparison(string $key)
 *
 * @see \Syriable\Metrics\Metrics
 */
class Metrics extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Syriable\Metrics\Metrics::class;
    }
}
