<?php

declare(strict_types=1);

use Syriable\Metrics\Enums\Direction;
use Syriable\Metrics\Support\Comparison;
use Syriable\Metrics\Support\Period;

function makeComparison(?float $current, ?float $previous): Comparison
{
    return new Comparison('previous_period', Period::between('2026-06-01', '2026-06-30'), $current, $previous);
}

it('computes difference, percentage and direction', function (): void {
    $comparison = makeComparison(150.0, 100.0);

    expect($comparison->difference)->toBe(50.0)
        ->and($comparison->percentage)->toBe(50.0)
        ->and($comparison->direction)->toBe(Direction::Up);
});

it('handles declines', function (): void {
    $comparison = makeComparison(75.0, 100.0);

    expect($comparison->difference)->toBe(-25.0)
        ->and($comparison->percentage)->toBe(-25.0)
        ->and($comparison->direction)->toBe(Direction::Down);
});

it('is flat when nothing changed', function (): void {
    expect(makeComparison(100.0, 100.0)->direction)->toBe(Direction::Flat);
});

it('yields no percentage when growing from zero, and zero when flat at zero', function (): void {
    expect(makeComparison(10.0, 0.0)->percentage)->toBeNull()
        ->and(makeComparison(0.0, 0.0)->percentage)->toBe(0.0);
});

it('yields no derived values when either side is unknown', function (): void {
    $comparison = makeComparison(null, 100.0);

    expect($comparison->difference)->toBeNull()
        ->and($comparison->percentage)->toBeNull()
        ->and($comparison->direction)->toBeNull();
});
