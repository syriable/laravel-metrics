<?php

declare(strict_types=1);

use Syriable\Metrics\Tests\Fixtures\Expense;
use Syriable\Metrics\Tests\Fixtures\Order;
use Syriable\Metrics\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * Create an order pinned to a creation instant (UTC storage).
 */
function order(string $createdAt, float $total = 0, string $status = 'paid', int $customerId = 1, float $refundTotal = 0): Order
{
    return Order::query()->create([
        'total' => $total,
        'status' => $status,
        'customer_id' => $customerId,
        'refund_total' => $refundTotal,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}

function expense(string $createdAt, float $amount): Expense
{
    return Expense::query()->create([
        'amount' => $amount,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}
