<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Syriable\Metrics\Enums\Interval;
use Syriable\Metrics\Exceptions\InvalidDefinitionException;
use Syriable\Metrics\Support\Period;

it('rejects a start after its end', function (): void {
    new Period(CarbonImmutable::parse('2026-07-10'), CarbonImmutable::parse('2026-07-01'));
})->throws(InvalidDefinitionException::class);

it('computes the previous period of a rolling window by exact duration', function (): void {
    $period = Period::between('2026-07-03 12:00:00', '2026-07-10 12:00:00');

    $previous = $period->previous();

    expect($previous->start->toDateTimeString())->toBe('2026-06-26 11:59:59')
        ->and($previous->end->toDateTimeString())->toBe('2026-07-03 11:59:59')
        ->and($previous->lengthInSeconds())->toBe($period->lengthInSeconds());
});

it('compares calendar-aligned periods against the elapsed portion', function (): void {
    // Month-to-date July 1–10 must compare against June 1–10, Nova-style.
    $period = new Period(
        CarbonImmutable::parse('2026-07-01 00:00:00'),
        CarbonImmutable::parse('2026-07-10 12:00:00'),
        Interval::Month,
    );

    $previous = $period->previous();

    expect($previous->start->toDateTimeString())->toBe('2026-06-01 00:00:00')
        ->and($previous->end->toDateTimeString())->toBe('2026-06-10 12:00:00');
});

it('shifts backward by calendar units without month overflow', function (): void {
    $period = Period::between('2026-03-31 00:00:00', '2026-03-31 23:59:59');

    $lastMonth = $period->shiftBackward(Interval::Month);
    $lastYear = $period->shiftBackward(Interval::Year);

    expect($lastMonth->start->toDateString())->toBe('2026-02-28')
        ->and($lastYear->start->toDateString())->toBe('2025-03-31');
});

it('serializes to a normalized array', function (): void {
    $period = Period::between('2026-07-01', '2026-07-10', 'Europe/Berlin');

    expect($period->toArray())->toBe([
        'start' => '2026-07-01T00:00:00+02:00',
        'end' => '2026-07-10T00:00:00+02:00',
        'timezone' => 'Europe/Berlin',
    ]);
});
