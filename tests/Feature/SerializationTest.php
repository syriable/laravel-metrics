<?php

declare(strict_types=1);

use Syriable\Metrics\Facades\Metrics;
use Syriable\Metrics\Tests\Fixtures\Order;

const SER_NOW = '2026-07-10 12:00:00';

it('serializes value metrics into a normalized API payload', function (): void {
    order('2026-07-05 10:00:00', total: 100);
    order('2026-05-20 10:00:00', total: 40);

    $payload = Metrics::query(Order::class)
        ->sum('total')
        ->range('30d')
        ->compareWithPrevious()
        ->as('orders.revenue')
        ->meta(['currency' => 'USD'])
        ->withNow(SER_NOW)
        ->value()
        ->toArray();

    expect($payload['key'])->toBe('orders.revenue')
        ->and($payload['type'])->toBe('value')
        ->and($payload['range']['key'])->toBe('30d')
        ->and($payload['range']['timezone'])->toBe('UTC')
        ->and($payload['interval'])->toBeNull()
        ->and($payload['datasets'][0]['name'])->toBe('default')
        ->and($payload['datasets'][0]['value'])->toBe(100)
        ->and($payload['datasets'][0]['comparison']['previous'])->toBe(40.0)
        ->and($payload['datasets'][0]['comparison']['direction'])->toBe('up')
        ->and($payload['datasets'][0]['comparison']['strategy'])->toBe('previous_period')
        ->and($payload['meta'])->toBe(['currency' => 'USD'])
        ->and($payload['from_cache'])->toBeFalse()
        ->and($payload)->toHaveKeys(['generated_at']);
});

it('serializes trend metrics with points and totals', function (): void {
    order('2026-07-09 10:00:00', total: 30);

    $payload = Metrics::query(Order::class)
        ->sum('total')
        ->range('3d')
        ->perDay()
        ->withNow(SER_NOW)
        ->trend()
        ->toArray();

    $days = collect($payload['datasets'][0]['points'])->keyBy('key');

    expect($payload['type'])->toBe('trend')
        ->and($payload['interval'])->toBe('day')
        ->and($days['2026-07-09']['value'])->toBe(30)
        ->and($days['2026-07-09']['label'])->toBe('Jul 9, 2026')
        ->and($days['2026-07-08']['value'])->toBe(0)
        ->and($payload['datasets'][0]['total'])->toBe(30);
});

it('serializes partition metrics with groups', function (): void {
    order('2026-07-05 10:00:00', status: 'paid');
    order('2026-07-06 10:00:00', status: 'refunded');

    $payload = Metrics::query(Order::class)
        ->count()
        ->groupBy('status')
        ->partition()
        ->toArray();

    expect($payload['type'])->toBe('partition')
        ->and(collect($payload['datasets'][0]['groups'])->pluck('percentage')->all())->toBe([50.0, 50.0]);
});

it('is JSON-encodable end to end', function (): void {
    order('2026-07-05 10:00:00');

    $json = json_encode(
        Metrics::query(Order::class)->count()->range('30d')->withNow(SER_NOW)->value()
    );

    expect($json)->toBeString()
        ->and(json_decode($json, true))->toHaveKeys(['key', 'type', 'range', 'datasets', 'generated_at']);
});
