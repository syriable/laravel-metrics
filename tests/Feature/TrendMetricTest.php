<?php

declare(strict_types=1);

use Syriable\Metrics\Enums\Interval;
use Syriable\Metrics\Enums\MetricType;
use Syriable\Metrics\Exceptions\InvalidDefinitionException;
use Syriable\Metrics\Facades\Metrics;
use Syriable\Metrics\Tests\Fixtures\Order;

const TREND_NOW = '2026-07-10 12:00:00';

it('produces a gap-filled daily series on canonical keys', function (): void {
    order('2026-07-08 09:00:00', total: 10);
    order('2026-07-08 15:00:00', total: 20);
    order('2026-07-10 08:00:00', total: 5);

    $result = Metrics::query(Order::class)
        ->sum('total')
        ->range('7d')
        ->perDay()
        ->withNow(TREND_NOW)
        ->trend();

    $points = $result->points();
    $byKey = collect($points)->keyBy->key;

    expect($result->type)->toBe(MetricType::Trend)
        ->and($points)->toHaveCount(8) // Jul 3 … Jul 10 inclusive
        ->and($points[0]->key)->toBe('2026-07-03')
        ->and($byKey['2026-07-08']->value)->toBe(30)
        ->and($byKey['2026-07-09']->value)->toBe(0) // gap-filled
        ->and($byKey['2026-07-10']->value)->toBe(5)
        ->and($result->dataset()->value)->toBe(35); // series total
});

it('buckets by week, month, quarter and year with matching keys', function (): void {
    order('2026-01-15 10:00:00');
    order('2026-04-02 10:00:00');
    order('2026-07-05 10:00:00');

    $keys = fn (Interval $interval) => collect(
        Metrics::query(Order::class)
            ->count()
            ->range('ytd')
            ->per($interval)
            ->withNow(TREND_NOW)
            ->trend()
            ->points()
    )->keyBy->key;

    $months = $keys(Interval::Month);
    $quarters = $keys(Interval::Quarter);
    $years = $keys(Interval::Year);
    $weeks = $keys(Interval::Week);

    expect($months['2026-01']->value)->toBe(1)
        ->and($months['2026-02']->value)->toBe(0)
        ->and($months['2026-07']->value)->toBe(1)
        ->and($quarters['2026-Q1']->value)->toBe(1)
        ->and($quarters['2026-Q2']->value)->toBe(1)
        ->and($quarters['2026-Q3']->value)->toBe(1)
        ->and($years['2026']->value)->toBe(3)
        ->and($weeks['2026-W03']->value)->toBe(1);
});

it('buckets by hour and minute', function (): void {
    order('2026-07-10 10:15:00');
    order('2026-07-10 10:45:00');
    order('2026-07-10 11:05:00');

    $hours = collect(
        Metrics::query(Order::class)
            ->count()
            ->range('6h')
            ->perHour()
            ->withNow(TREND_NOW)
            ->trend()
            ->points()
    )->keyBy->key;

    expect($hours['2026-07-10 10:00']->value)->toBe(2)
        ->and($hours['2026-07-10 11:00']->value)->toBe(1);
});

it('shifts buckets into the display timezone', function (): void {
    // 20:00 UTC on Jul 9 is 01:30 on Jul 10 in Asia/Kolkata (UTC+5:30).
    order('2026-07-09 20:00:00');

    $result = Metrics::query(Order::class)
        ->count()
        ->range('7d')
        ->perDay()
        ->timezone('Asia/Kolkata')
        ->withNow(TREND_NOW)
        ->trend();

    $byKey = collect($result->points())->keyBy->key;

    expect($byKey['2026-07-10']->value)->toBe(1)
        ->and($byKey['2026-07-09']->value)->toBe(0);
});

it('attaches a totals comparison to trends when requested', function (): void {
    order('2026-07-08 10:00:00', total: 100);
    order('2026-06-28 10:00:00', total: 40); // inside the preceding 7d window

    $result = Metrics::query(Order::class)
        ->sum('total')
        ->range('7d')
        ->perDay()
        ->compareWithPrevious()
        ->withNow(TREND_NOW)
        ->trend();

    expect($result->comparison()->previous)->toBe(40.0)
        ->and($result->comparison()->difference)->toBe(60.0);
});

it('labels points for humans without using labels as join keys', function (): void {
    order('2026-07-10 08:00:00');

    $result = Metrics::query(Order::class)
        ->count()
        ->range('1d')
        ->perDay()
        ->withNow(TREND_NOW)
        ->trend();

    $last = collect($result->points())->last();

    expect($last->label)->toBe('Jul 10, 2026')
        ->and($last->key)->toBe('2026-07-10');
});

it('refuses unbounded trends', function (): void {
    Metrics::query(Order::class)->count()->allTime()->perDay()->trend();
})->throws(InvalidDefinitionException::class, 'bounded range');

it('caps the number of buckets', function (): void {
    config()->set('metrics.max_buckets', 10);

    Metrics::query(Order::class)->count()->range('30d')->perHour()->withNow(TREND_NOW)->trend();
})->throws(InvalidDefinitionException::class, 'buckets');
