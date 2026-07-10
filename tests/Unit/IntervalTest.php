<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Syriable\Metrics\Enums\Interval;

it('produces canonical bucket keys', function (): void {
    $date = CarbonImmutable::parse('2026-07-10 14:37:22');

    expect(Interval::Minute->key($date))->toBe('2026-07-10 14:37')
        ->and(Interval::Hour->key($date))->toBe('2026-07-10 14:00')
        ->and(Interval::Day->key($date))->toBe('2026-07-10')
        ->and(Interval::Week->key($date))->toBe('2026-W28')
        ->and(Interval::Month->key($date))->toBe('2026-07')
        ->and(Interval::Quarter->key($date))->toBe('2026-Q3')
        ->and(Interval::Year->key($date))->toBe('2026');
});

it('uses the ISO week-year for week keys at year boundaries', function (): void {
    // Dec 29 2025 belongs to ISO week 2026-W01.
    expect(Interval::Week->key(CarbonImmutable::parse('2025-12-29')))->toBe('2026-W01')
        // Jan 1 2027 belongs to ISO week 2026-W53.
        ->and(Interval::Week->key(CarbonImmutable::parse('2027-01-01')))->toBe('2026-W53');
});

it('truncates to bucket starts', function (): void {
    $date = CarbonImmutable::parse('2026-07-10 14:37:22');

    expect(Interval::Week->truncate($date)->toDateTimeString())->toBe('2026-07-06 00:00:00')
        ->and(Interval::Quarter->truncate($date)->toDateTimeString())->toBe('2026-07-01 00:00:00')
        ->and(Interval::Year->truncate($date)->toDateTimeString())->toBe('2026-01-01 00:00:00');
});

it('advances by one bucket', function (): void {
    $start = CarbonImmutable::parse('2026-01-01 00:00:00');

    expect(Interval::Quarter->advance($start)->toDateString())->toBe('2026-04-01')
        ->and(Interval::Month->advance($start)->toDateString())->toBe('2026-02-01')
        ->and(Interval::Week->advance($start)->toDateString())->toBe('2026-01-08');
});
