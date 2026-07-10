<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Syriable\Metrics\Enums\Direction;
use Syriable\Metrics\Enums\MetricType;
use Syriable\Metrics\Exceptions\InvalidDefinitionException;
use Syriable\Metrics\Facades\Metrics;
use Syriable\Metrics\Tests\Fixtures\Order;

const NOW = '2026-07-10 12:00:00';

it('counts records in a rolling range', function (): void {
    order('2026-07-01 10:00:00');
    order('2026-07-05 10:00:00');
    order('2026-05-01 10:00:00'); // outside 30d

    $result = Metrics::query(Order::class)
        ->count()
        ->range('30d')
        ->withNow(NOW)
        ->value();

    expect($result->value())->toBe(2)
        ->and($result->type)->toBe(MetricType::Value)
        ->and($result->rangeKey)->toBe('30d');
});

it('supports sum, average, min, max and distinct count', function (): void {
    order('2026-07-01 10:00:00', total: 100, customerId: 1);
    order('2026-07-02 10:00:00', total: 50, customerId: 2);
    order('2026-07-03 10:00:00', total: 30, customerId: 2);

    $metric = fn () => Metrics::query(Order::class)->range('30d')->withNow(NOW);

    expect($metric()->sum('total')->value()->value())->toBe(180)
        ->and($metric()->average('total')->value()->value())->toBe(60)
        ->and($metric()->min('total')->value()->value())->toBe(30)
        ->and($metric()->max('total')->value()->value())->toBe(100)
        ->and($metric()->countDistinct('customer_id')->value()->value())->toBe(2);
});

it('aggregates all time when the range is removed', function (): void {
    order('2020-01-01 10:00:00');
    order('2026-07-05 10:00:00');

    $result = Metrics::query(Order::class)->count()->allTime()->value();

    expect($result->value())->toBe(2)
        ->and($result->rangeKey)->toBe('all')
        ->and($result->period)->toBeNull();
});

it('compares against the previous period in a single query', function (): void {
    order('2026-07-05 10:00:00', total: 100); // current 30d window
    order('2026-07-08 10:00:00', total: 50);
    order('2026-05-20 10:00:00', total: 60);  // previous 30d window

    DB::connection()->enableQueryLog();

    $result = Metrics::query(Order::class)
        ->sum('total')
        ->range('30d')
        ->compareWithPrevious()
        ->withNow(NOW)
        ->value();

    $queries = DB::connection()->getQueryLog();
    DB::connection()->disableQueryLog();

    $comparison = $result->comparison();

    expect(count($queries))->toBe(1)
        ->and($result->value())->toBe(150)
        ->and($comparison->previous)->toBe(60.0)
        ->and($comparison->difference)->toBe(90.0)
        ->and($comparison->percentage)->toBe(150.0)
        ->and($comparison->direction)->toBe(Direction::Up);
});

it('compares month-to-date against the elapsed portion of last month', function (): void {
    order('2026-07-03 10:00:00', total: 10);  // current MTD
    order('2026-06-05 10:00:00', total: 40);  // elapsed portion of June (1st–10th)
    order('2026-06-25 10:00:00', total: 999); // later in June — must be excluded

    $result = Metrics::query(Order::class)
        ->sum('total')
        ->range('mtd')
        ->compareWithPrevious()
        ->withNow(NOW)
        ->value();

    expect($result->value())->toBe(10)
        ->and($result->comparison()->previous)->toBe(40.0);
});

it('compares against the same period last year', function (): void {
    order('2026-07-05 10:00:00');
    order('2025-07-01 10:00:00');
    order('2025-07-02 10:00:00');

    $result = Metrics::query(Order::class)
        ->count()
        ->range('mtd')
        ->compareWithPreviousYear()
        ->withNow(NOW)
        ->value();

    expect($result->value())->toBe(1)
        ->and($result->comparison()->previous)->toBe(2.0)
        ->and($result->comparison()->strategy)->toBe('previous_year');
});

it('supports explicit periods', function (): void {
    order('2026-03-05 10:00:00');
    order('2026-03-20 10:00:00');
    order('2026-04-02 10:00:00');

    $result = Metrics::query(Order::class)
        ->count()
        ->between('2026-03-01 00:00:00', '2026-03-31 23:59:59')
        ->value();

    expect($result->value())->toBe(2);
});

it('scopes queries at metric level', function (): void {
    order('2026-07-05 10:00:00', status: 'paid');
    order('2026-07-06 10:00:00', status: 'cancelled');

    $result = Metrics::query(Order::class)
        ->query(fn ($query) => $query->where('status', 'paid'))
        ->count()
        ->range('30d')
        ->withNow(NOW)
        ->value();

    expect($result->value())->toBe(1);
});

it('honors precision settings', function (): void {
    order('2026-07-01 10:00:00', total: 10);
    order('2026-07-02 10:00:00', total: 5);
    order('2026-07-03 10:00:00', total: 5);

    $result = Metrics::query(Order::class)
        ->average('total')
        ->range('30d')
        ->precision(1)
        ->withNow(NOW)
        ->value();

    expect($result->value())->toBe(6.7);
});

it('returns the aggregate-appropriate empty value when no rows match', function (): void {
    $metric = fn () => Metrics::query(Order::class)->range('30d')->withNow(NOW);

    expect($metric()->count()->value()->value())->toBe(0)
        ->and($metric()->sum('total')->value()->value())->toBe(0)
        ->and($metric()->average('total')->value()->value())->toBeNull();
});

it('rejects comparisons on all-time metrics', function (): void {
    Metrics::query(Order::class)->count()->allTime()->compareWithPrevious()->value();
})->throws(InvalidDefinitionException::class, 'reference period');

it('rejects column-requiring aggregates without a column', function (): void {
    Metrics::query(Order::class)->aggregate('sum')->range('30d')->value();
})->throws(InvalidDefinitionException::class, 'requires a column');
