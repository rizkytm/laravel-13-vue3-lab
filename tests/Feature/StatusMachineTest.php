<?php

use App\Enums\MovementType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;

// Status machine: semua perubahan status melewati Order::transitionTo.
// Transisi tidak sah → false, status tidak berubah.

it('allows pending → paid, cancelled, and failed', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    expect(OrderStatus::Pending->canTransitionTo(OrderStatus::Paid))->toBeTrue()
        ->and(OrderStatus::Pending->canTransitionTo(OrderStatus::Cancelled))->toBeTrue()
        ->and(OrderStatus::Pending->canTransitionTo(OrderStatus::Failed))->toBeTrue();
});

it('forbids every transition out of truly terminal states (cancelled, failed, refunded)', function () {
    foreach ([OrderStatus::Cancelled, OrderStatus::Failed, OrderStatus::Refunded] as $from) {
        foreach (OrderStatus::cases() as $to) {
            expect($from->canTransitionTo($to))->toBeFalse("{$from->value} → {$to->value} seharusnya dilarang");
        }
    }
});

it('allows paid → refunded and nothing else from paid', function () {
    foreach (OrderStatus::cases() as $to) {
        expect(OrderStatus::Paid->canTransitionTo($to))->toBe($to === OrderStatus::Refunded,
            "paid → {$to->value}");
    }
});

it('transitionTo updates status and returns true for a valid transition', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    expect($order->transitionTo(OrderStatus::Paid, ['transaction_id' => 'txn_9', 'paid_at' => now()]))->toBeTrue()
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->fresh()->transaction_id)->toBe('txn_9')
        ->and($order->fresh()->paid_at)->not->toBeNull();
});

it('transitionTo refuses invalid transition and leaves status untouched', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Cancelled]);

    expect($order->transitionTo(OrderStatus::Paid))->toBeFalse()
        ->and($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->fresh()->paid_at)->toBeNull();
});

it('cancel transitions to cancelled AND restocks items with a movement trace', function () {
    $product = Product::factory()->create(['stock' => 3]);
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 2]);

    // Simulasi checkout: stok sudah berkurang 2 (sisa 1)
    $product->buy(2, $order);

    expect($order->cancel())->toBeTrue()
        ->and($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($product->fresh()->stock)->toBe(3); // stok kembali penuh

    // Jejak: gerakan 'in' +2 terkait order — stok tidak kembali tanpa jejak
    $movement = $product->movements()->latest('id')->first();
    expect($movement->type)->toBe(MovementType::In)
        ->and($movement->quantity)->toBe(2)
        ->and($movement->order_id)->toBe($order->id)
        ->and($movement->stock_after)->toBe(3);
});

it('cancel refuses when status machine forbids it and keeps stock', function () {
    $product = Product::factory()->create(['stock' => 3]);
    $order = Order::factory()->create(['status' => OrderStatus::Paid]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 2]);

    expect($order->cancel())->toBeFalse()
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($product->fresh()->stock)->toBe(3); // tidak ada restock ilegal
});

it('keeps the SUM(movements) == stock invariant through buy and cancel', function () {
    $product = Product::factory()->create(['stock' => 5]);
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 2]);

    $product->buy(2, $order);
    expect($product->fresh()->stock)->toBe(3);

    $order->cancel();
    expect($product->fresh()->stock)->toBe(5);

    $sum = $product->movements()->sum('quantity');
    expect((int) $sum)->toBe($product->fresh()->stock);
});
