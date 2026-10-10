<?php

use App\Services\Orders\OrderSummary;

// Unit test murni: tidak butuh Application/database — cukup object-nya.

it('summarizes orders by status', function () {
    $summary = (new OrderSummary)->make([
        ['id' => 1, 'status' => 'paid', 'total' => 100000, 'item_count' => 2],
        ['id' => 2, 'status' => 'pending', 'total' => 50000, 'item_count' => 1],
        ['id' => 3, 'status' => 'paid', 'total' => 200000, 'item_count' => 3],
    ]);

    expect($summary['total_orders'])->toBe(3)
        ->and($summary['total_revenue'])->toBe(300000) // hanya status paid
        ->and($summary['by_status']['paid'])->toBe(2)
        ->and($summary['by_status']['pending'])->toBe(1)
        ->and($summary['avg_items'])->toBe(2.0);
});

it('returns zeroed summary for empty orders', function () {
    $summary = (new OrderSummary)->make([]);

    expect($summary['total_orders'])->toBe(0)
        ->and($summary['total_revenue'])->toBe(0)
        ->and($summary['by_status'])->toBe([])
        ->and($summary['avg_items'])->toBe(0.0);
});
