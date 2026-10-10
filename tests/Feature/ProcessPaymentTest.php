<?php

use App\Enums\OrderStatus;
use App\Jobs\ProcessPayment;
use App\Models\Order;
use App\Services\Payment\PaymentGateway;
use RuntimeException;

// Queue job: retry, idempotency, failed() hook.

it('charges gateway and marks order paid', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending, 'total' => 40000]);

    (new ProcessPayment($order))->handle(app(PaymentGateway::class));

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->transaction_id)->toBe('txn_1')
        ->and($order->paid_at)->not->toBeNull()
        ->and(app(PaymentGateway::class)->charges)->toBe([40000]);
});

it('is idempotent — repeated handle does not double charge', function () {
    $order = Order::factory()->create(['total' => 40000]);
    $job = new ProcessPayment($order);

    $job->handle(app(PaymentGateway::class));
    $job->handle(app(PaymentGateway::class)); // simulasi retry setelah sukses

    expect(app(PaymentGateway::class)->charges)->toHaveCount(1)
        ->and($order->refresh()->status)->toBe(OrderStatus::Paid);
});

it('marks order failed when all retries exhausted', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    (new ProcessPayment($order))->failed(new RuntimeException('gateway down'));

    expect($order->refresh()->status)->toBe(OrderStatus::Failed);
});

it('uses retry budget with backoff (tries=3, backoff naik)', function () {
    $job = new ProcessPayment(Order::factory()->create());

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([10, 30, 60]);
});
