<?php

declare(strict_types=1);

namespace Syriable\Metrics;

use Illuminate\Support\Str;
use Syriable\Metrics\Builder\MetricBuilder;
use Syriable\Metrics\Results\MetricResult;

/**
 * A reusable, named metric definition — the class-based alternative to
 * inline builders, and the unit an API endpoint exposes:
 *
 *     class OrdersRevenue extends Metric
 *     {
 *         public function query(): MetricBuilder
 *         {
 *             return Metrics::query(Order::class)
 *                 ->sum('total')
 *                 ->range('30d')
 *                 ->compareWithPrevious();
 *         }
 *     }
 *
 *     Metrics::register(OrdersRevenue::class);
 *     Metrics::run('orders_revenue', ['range' => 'mtd']);
 *
 * Unlike Nova metrics, this class knows nothing about HTTP, cards, or
 * components — it is a pure definition.
 */
abstract class Metric
{
    /**
     * The stable identifier the metric is registered and executed by.
     */
    public function key(): string
    {
        return Str::snake(class_basename(static::class));
    }

    /**
     * A human-readable name for listings.
     */
    public function label(): string
    {
        return Str::headline(class_basename(static::class));
    }

    /**
     * Describe the metric. Called on every execution; context overrides
     * (range, interval, timezone, comparison) are applied on top.
     */
    abstract public function query(): MetricBuilder;

    /**
     * Convenience: execute this metric directly.
     *
     * @param  array{range?: string, interval?: string, timezone?: string, compare?: string, fresh?: bool}  $context
     */
    public function run(array $context = []): MetricResult
    {
        return app(Metrics::class)->register($this)->run($this->key(), $context);
    }
}
