<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Syriable\Metrics\Facades\Metrics;
use Syriable\Metrics\Tests\Fixtures\Order;

const CACHE_NOW = '2026-07-10 12:00:00';

function cachedCountMetric(string $range = '30d')
{
    return Metrics::query(Order::class)
        ->count()
        ->range($range)
        ->withNow(CACHE_NOW)
        ->cache(600);
}

it('serves repeated executions from cache without re-querying', function (): void {
    order('2026-07-05 10:00:00');

    $first = cachedCountMetric()->value();

    DB::connection()->enableQueryLog();
    $second = cachedCountMetric()->value();
    $queries = DB::connection()->getQueryLog();
    DB::connection()->disableQueryLog();

    expect($first->fromCache)->toBeFalse()
        ->and($second->fromCache)->toBeTrue()
        ->and($second->value())->toBe(1)
        // The only query allowed on a hit is none at all — even the cache
        // key derives from compiled SQL, not from executed SQL.
        ->and(collect($queries)->filter(fn ($q) => str_contains($q['query'], 'count')))->toHaveCount(0);
});

it('varies the cache by range and definition', function (): void {
    order('2026-07-05 10:00:00');
    order('2026-06-25 10:00:00');

    expect(cachedCountMetric('7d')->value()->value())->toBe(1)
        ->and(cachedCountMetric('30d')->value()->value())->toBe(2);
});

it('bypasses the cache with fresh()', function (): void {
    order('2026-07-05 10:00:00');
    cachedCountMetric()->value();

    order('2026-07-06 10:00:00');

    expect(cachedCountMetric()->value()->value())->toBe(1)          // stale hit
        ->and(cachedCountMetric()->fresh()->value()->value())->toBe(2); // bypass
});

it('caches everything when a global TTL is configured', function (): void {
    config()->set('metrics.cache.ttl', 600);
    order('2026-07-05 10:00:00');

    $plain = fn () => Metrics::query(Order::class)->count()->range('30d')->withNow(CACHE_NOW)->value();

    expect($plain()->fromCache)->toBeFalse()
        ->and($plain()->fromCache)->toBeTrue();
});

it('preserves generated_at across cache hits', function (): void {
    order('2026-07-05 10:00:00');

    $first = cachedCountMetric()->value();
    $second = cachedCountMetric()->value();

    expect($second->fromCache)->toBeTrue()
        ->and($second->generatedAt->equalTo($first->generatedAt))->toBeTrue()
        ->and($second->generatedAt->toIso8601String())->toBe(CarbonImmutable::parse(CACHE_NOW)->toIso8601String());
});
