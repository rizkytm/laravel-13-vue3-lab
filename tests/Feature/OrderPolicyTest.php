<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;

it('lets the owner view own order detail', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();
    OrderItem::factory()->create(['order_id' => $order->id]);

    $this->actingAs($user)
        ->get("/orders/{$order->id}")
        ->assertOk()
        ->assertSee($order->user->name);
});

it('forbids other users from viewing the order (403)', function () {
    $order = Order::factory()->create(); // milik user lain
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->get("/orders/{$order->id}")
        ->assertForbidden();
});

it('redirects guest to login instead of showing order', function () {
    $order = Order::factory()->create();

    $this->get("/orders/{$order->id}")->assertRedirect(route('login'));
});
