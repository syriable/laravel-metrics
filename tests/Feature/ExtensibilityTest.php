<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Syriable\Metrics\Aggregates\CallbackAggregate;
use Syriable\Metrics\Comparisons\CallbackComparison;
use Syriable\Metrics\Contracts\Serializer;
use Syriable\Metrics\Exceptions\UnknownAggregateException;
use Syriable\Metrics\Exceptions\UnknownRangeException;
use Syriable\Metrics\Facades\Metrics;
use Syriable\Metrics\Ranges\CallbackRange;
use Syriable\Metrics\Results\MetricResult;
use Syriable\Metrics\Support\Period;
use Syriable\Metrics\Tests\Fixtures\Order;

const EXT_NOW = '2026-07-10 12:00:00';

it('runs custom aggregates registered at runtime', function (): void {
    // "Total" as sum of doubled values — contrived, but proves arbitrary
    // SQL aggregation plugs in without touching the engine.
    Metrics::registerAggregate(new CallbackAggregate(
        'double_sum',
        static fn (string $inner): string => "sum(({$inner}) * 2)",
        requiresColumn: true,
        emptyValue: 0,
    ));

    order('2026-07-05 10:00:00', total: 10);
    order('2026-07-06 10:00:00', total: 5);

    $result = Metrics::query(Order::class)
        ->aggregate('double_sum', 'total')
        ->range('30d')
        ->withNow(EXT_NOW)
        ->value();

    expect($result->value())->toBe(30);
});

it('runs custom named ranges', function (): void {
    Metrics::registerRange(new CallbackRange(
        'launch_window',
        'Launch window',
        static fn (CarbonImmutable $now): Period => Period::between('2026-07-01 00:00:00', '2026-07-04 23:59:59'),
    ));

    order('2026-07-02 10:00:00');
    order('2026-07-08 10:00:00');

    $result = Metrics::query(Order::class)->count()->range('launch_window')->value();

    expect($result->value())->toBe(1)
        ->and($result->rangeKey)->toBe('launch_window');
});

it('runs custom comparison strategies', function (): void {
    Metrics::registerComparison(new CallbackComparison(
        'vs_launch',
        static fn (Period $current): Period => Period::between('2026-07-01 00:00:00', '2026-07-02 23:59:59'),
    ));

    order('2026-07-01 10:00:00');
    order('2026-07-01 11:00:00');
    order('2026-07-09 14:00:00'); // inside the rolling 1d window ending Jul 10 12:00

    $result = Metrics::query(Order::class)
        ->count()
        ->range('1d')
        ->compareWith('vs_launch')
        ->withNow(EXT_NOW)
        ->value();

    expect($result->value())->toBe(1)
        ->and($result->comparison()->previous)->toBe(2.0)
        ->and($result->comparison()->strategy)->toBe('vs_launch');
});

it('projects through a custom serializer', function (): void {
    Metrics::useSerializer(new class implements Serializer
    {
        public function serialize(MetricResult $result): array
        {
            return ['flat_value' => $result->value()];
        }
    });

    order('2026-07-05 10:00:00');

    $payload = Metrics::query(Order::class)
        ->count()
        ->range('30d')
        ->withNow(EXT_NOW)
        ->value()
        ->toArray();

    expect($payload)->toBe(['flat_value' => 1]);
});

it('fails loudly on unknown vocabulary', function (): void {
    expect(fn () => Metrics::query(Order::class)->aggregate('median')->range('30d')->value())
        ->toThrow(UnknownAggregateException::class)
        ->and(fn () => Metrics::query(Order::class)->count()->range('fortnight')->value())
        ->toThrow(UnknownRangeException::class);
});

it('resolves rolling range patterns dynamically', function (): void {
    order('2026-07-09 10:00:00');
    order('2026-07-01 10:00:00');
    order('2026-05-15 10:00:00');

    $count = fn (string $range) => Metrics::query(Order::class)
        ->count()->range($range)->withNow(EXT_NOW)->value()->value();

    expect($count('2d'))->toBe(1)
        ->and($count('2w'))->toBe(2)
        ->and($count('2mo'))->toBe(3)
        ->and($count('48h'))->toBe(1);
});
