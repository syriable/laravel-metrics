<?php

declare(strict_types=1);

use Syriable\Metrics\Builder\DatasetBuilder;
use Syriable\Metrics\Exceptions\InvalidDefinitionException;
use Syriable\Metrics\Facades\Metrics;
use Syriable\Metrics\Tests\Fixtures\Expense;
use Syriable\Metrics\Tests\Fixtures\Order;

const DS_NOW = '2026-07-10 12:00:00';

it('computes multiple datasets and a formula in one metric', function (): void {
    order('2026-07-01 10:00:00', total: 100, refundTotal: 10);
    order('2026-07-02 10:00:00', total: 50, refundTotal: 0);

    $result = Metrics::query(Order::class)
        ->range('30d')
        ->withNow(DS_NOW)
        ->dataset('revenue', fn (DatasetBuilder $d) => $d->sum('total'))
        ->dataset('refunds', fn (DatasetBuilder $d) => $d->sum('refund_total'))
        ->formula('net', '[revenue] - [refunds]')
        ->value();

    expect($result->value('revenue'))->toBe(150)
        ->and($result->value('refunds'))->toBe(10)
        ->and($result->value('net'))->toBe(140)
        ->and($result->dataset('net')->formula)->toBeTrue();
});

it('mixes sources across datasets', function (): void {
    order('2026-07-01 10:00:00', total: 200);
    expense('2026-07-02 10:00:00', amount: 80);

    $result = Metrics::query(Order::class)
        ->range('30d')
        ->withNow(DS_NOW)
        ->dataset('revenue', fn (DatasetBuilder $d) => $d->sum('total'))
        ->dataset('expenses', fn (DatasetBuilder $d) => $d->sum('amount')->from(Expense::class))
        ->formula('profit', 'revenue - expenses')
        ->formula('margin', 'profit / revenue * 100')
        ->value();

    expect($result->value('revenue'))->toBe(200)
        ->and($result->value('expenses'))->toBe(80)
        ->and($result->value('profit'))->toBe(120)
        ->and($result->value('margin'))->toBe(60);
});

it('constrains datasets independently', function (): void {
    order('2026-07-01 10:00:00', total: 100, status: 'paid');
    order('2026-07-02 10:00:00', total: 40, status: 'refunded');

    $result = Metrics::query(Order::class)
        ->range('30d')
        ->withNow(DS_NOW)
        ->dataset('paid', fn (DatasetBuilder $d) => $d->sum('total')->query(fn ($q) => $q->where('status', 'paid')))
        ->dataset('refunded', fn (DatasetBuilder $d) => $d->sum('total')->query(fn ($q) => $q->where('status', 'refunded')))
        ->value();

    expect($result->value('paid'))->toBe(100)
        ->and($result->value('refunded'))->toBe(40);
});

it('evaluates formulas pointwise across trend datasets', function (): void {
    order('2026-07-08 10:00:00', total: 100, refundTotal: 30);
    order('2026-07-09 10:00:00', total: 50, refundTotal: 5);

    $result = Metrics::query(Order::class)
        ->range('7d')
        ->perDay()
        ->withNow(DS_NOW)
        ->dataset('revenue', fn (DatasetBuilder $d) => $d->sum('total'))
        ->dataset('refunds', fn (DatasetBuilder $d) => $d->sum('refund_total'))
        ->formula('net', 'revenue - refunds')
        ->trend();

    $net = collect($result->points('net'))->keyBy->key;

    expect($net['2026-07-08']->value)->toBe(70)
        ->and($net['2026-07-09']->value)->toBe(45)
        ->and($net['2026-07-07']->value)->toBe(0)
        ->and($result->dataset('net')->value)->toBe(115);
});

it('evaluates formulas per group across partition datasets', function (): void {
    order('2026-07-01 10:00:00', total: 100, refundTotal: 20, status: 'a');
    order('2026-07-02 10:00:00', total: 50, refundTotal: 10, status: 'b');

    $result = Metrics::query(Order::class)
        ->groupBy('status')
        ->dataset('revenue', fn (DatasetBuilder $d) => $d->sum('total'))
        ->dataset('refunds', fn (DatasetBuilder $d) => $d->sum('refund_total'))
        ->formula('net', 'revenue - refunds')
        ->partition();

    $net = collect($result->groups('net'))->keyBy->key;

    expect($net['a']->value)->toBe(80)
        ->and($net['b']->value)->toBe(40);
});

it('carries comparisons through formulas', function (): void {
    order('2026-07-05 10:00:00', total: 100, refundTotal: 20); // current
    order('2026-05-25 10:00:00', total: 60, refundTotal: 10);  // previous window

    $result = Metrics::query(Order::class)
        ->range('30d')
        ->withNow(DS_NOW)
        ->compareWithPrevious()
        ->dataset('revenue', fn (DatasetBuilder $d) => $d->sum('total'))
        ->dataset('refunds', fn (DatasetBuilder $d) => $d->sum('refund_total'))
        ->formula('net', 'revenue - refunds')
        ->value();

    expect($result->value('net'))->toBe(80)
        ->and($result->comparison('net')->previous)->toBe(50.0)
        ->and($result->comparison('revenue')->previous)->toBe(60.0);
});

it('rejects duplicate dataset names', function (): void {
    Metrics::query(Order::class)
        ->dataset('x', fn (DatasetBuilder $d) => $d->count())
        ->formula('x', '1 + 1');
})->throws(InvalidDefinitionException::class, 'already defined');
