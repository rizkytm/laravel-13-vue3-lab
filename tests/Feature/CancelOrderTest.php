<?php

use App\Enums\MovementType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

// Tahap 8 — customer cancel order pending: status machine + restock berjejak.

it('lets the owner cancel a pending order and restocks with a trace', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['stock' => 5]);
    $order = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::Pending, 'total' => 50000]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 2]);
    $product->buy(2, $order); // simulasi checkout → stok 3

    Sanctum::actingAs($user);

    $this->postJson("/api/v1/orders/{$order->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($product->fresh()->stock)->toBe(5); // stok kembali

    // Jejak movement 'in' terkait order
    $movement = $product->movements()->latest('id')->first();
    expect($movement->type)->toBe(MovementType::In)
        ->and($movement->quantity)->toBe(2)
        ->and($movement->order_id)->toBe($order->id);
});

it('keeps SUM(movements) == stock after customer cancel', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['stock' => 5]);
    $order = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::Pending]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 3]);
    $product->buy(3, $order);

    Sanctum::actingAs($user);
    $this->postJson("/api/v1/orders/{$order->id}/cancel")->assertOk();

    expect((int) $product->movements()->sum('quantity'))->toBe($product->fresh()->stock);
});

it('forbids cancelling a paid order (needs admin refund)', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::Paid]);

    Sanctum::actingAs($user);

    $this->postJson("/api/v1/orders/{$order->id}/cancel")->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('forbids cancelling another users order', function () {
    $owner = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $owner->id, 'status' => OrderStatus::Pending]);

    Sanctum::actingAs(User::factory()->create()); // bukan pemilik

    $this->postJson("/api/v1/orders/{$order->id}/cancel")->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('requires authentication', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $this->postJson("/api/v1/orders/{$order->id}/cancel")->assertUnauthorized();
});

it('refuses double cancel (cancelled is terminal)', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['stock' => 5]);
    $order = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::Pending]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 2]);
    $product->buy(2, $order);

    Sanctum::actingAs($user);

    // Kedua kalinya policy sudah menolak (bukan pending lagi) → 403
    $this->postJson("/api/v1/orders/{$order->id}/cancel")->assertOk();
    $this->postJson("/api/v1/orders/{$order->id}/cancel")->assertForbidden();

    // Stok tidak double-restock
    expect($product->fresh()->stock)->toBe(5);
});
