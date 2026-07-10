<?php

declare(strict_types=1);

use Syriable\Metrics\Enums\MetricType;
use Syriable\Metrics\Exceptions\InvalidDefinitionException;
use Syriable\Metrics\Facades\Metrics;
use Syriable\Metrics\Tests\Fixtures\Order;

const PARTITION_NOW = '2026-07-10 12:00:00';

it('partitions counts by a column with engine-computed percentages', function (): void {
    order('2026-07-01 10:00:00', status: 'paid');
    order('2026-07-02 10:00:00', status: 'paid');
    order('2026-07-03 10:00:00', status: 'paid');
    order('2026-07-04 10:00:00', status: 'refunded');

    $result = Metrics::query(Order::class)
        ->count()
        ->groupBy('status')
        ->partition();

    $groups = collect($result->groups())->keyBy->key;

    expect($result->type)->toBe(MetricType::Partition)
        ->and($groups)->toHaveCount(2)
        ->and($groups['paid']->value)->toBe(3)
        ->and($groups['paid']->percentage)->toBe(75.0)
        ->and($groups['refunded']->percentage)->toBe(25.0)
        ->and($result->dataset()->value)->toBe(4);
});

it('spans all time by default but honors an explicit range', function (): void {
    order('2020-01-01 10:00:00', status: 'legacy');
    order('2026-07-05 10:00:00', status: 'paid');

    $allTime = Metrics::query(Order::class)->count()->groupBy('status')->partition();

    $ranged = Metrics::query(Order::class)
        ->count()
        ->groupBy('status')
        ->range('30d')
        ->withNow(PARTITION_NOW)
        ->partition();

    expect(collect($allTime->groups()))->toHaveCount(2)
        ->and(collect($ranged->groups()))->toHaveCount(1);
});

it('partitions sums ordered by size', function (): void {
    order('2026-07-01 10:00:00', total: 10, status: 'small');
    order('2026-07-02 10:00:00', total: 100, status: 'big');

    $groups = Metrics::query(Order::class)
        ->sum('total')
        ->groupBy('status')
        ->partition()
        ->groups();

    expect($groups[0]->key)->toBe('big')
        ->and($groups[0]->value)->toBe(100);
});

it('folds the tail into an "others" group', function (): void {
    foreach (['a' => 50, 'b' => 30, 'c' => 10, 'd' => 5] as $status => $total) {
        order('2026-07-01 10:00:00', total: $total, status: $status);
    }

    $groups = collect(
        Metrics::query(Order::class)
            ->sum('total')
            ->groupBy('status')
            ->top(2)
            ->partition()
            ->groups()
    )->keyBy->key;

    expect($groups)->toHaveCount(3)
        ->and($groups['a']->value)->toBe(50)
        ->and($groups['b']->value)->toBe(30)
        ->and($groups['others']->value)->toBe(15);
});

it('requires a groupBy column', function (): void {
    Metrics::query(Order::class)->count()->partition();
})->throws(InvalidDefinitionException::class, 'groupBy');
