<?php

declare(strict_types=1);

use Syriable\Metrics\Builder\MetricBuilder;
use Syriable\Metrics\Enums\MetricType;
use Syriable\Metrics\Exceptions\InvalidDefinitionException;
use Syriable\Metrics\Facades\Metrics;
use Syriable\Metrics\Metric;
use Syriable\Metrics\Tests\Fixtures\Order;

const REG_NOW = '2026-07-10 12:00:00';

class OrdersRevenue extends Metric
{
    public function query(): MetricBuilder
    {
        return Metrics::query(Order::class)
            ->sum('total')
            ->range('30d')
            ->withNow(REG_NOW);
    }
}

it('registers and runs metrics by key', function (): void {
    order('2026-07-05 10:00:00', total: 120);

    Metrics::register(OrdersRevenue::class);

    $result = Metrics::run('orders_revenue');

    expect($result->value())->toBe(120)
        ->and($result->key)->toBe('orders_revenue')
        ->and(Metrics::registered())->toHaveKey('orders_revenue');
});

it('applies API-style context overrides on top of the definition', function (): void {
    order('2026-07-10 08:00:00', total: 10);
    order('2026-06-05 10:00:00', total: 99);

    Metrics::register(new OrdersRevenue);

    $ranged = Metrics::run('orders_revenue', ['range' => 'today']);
    $trend = Metrics::run('orders_revenue', ['range' => '7d', 'interval' => 'day']);

    expect($ranged->value())->toBe(10)
        ->and($trend->type)->toBe(MetricType::Trend)
        ->and($ranged->rangeKey)->toBe('today');
});

it('throws for unregistered metrics', function (): void {
    Metrics::run('nope');
})->throws(InvalidDefinitionException::class, 'No metric registered');

it('derives key and label from the class name', function (): void {
    $metric = new OrdersRevenue;

    expect($metric->key())->toBe('orders_revenue')
        ->and($metric->label())->toBe('Orders Revenue');
});
