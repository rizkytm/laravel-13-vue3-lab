<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('lists own orders with user and items rendered', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();
    OrderItem::factory()->count(2)->create(['order_id' => $order->id]);

    $this->actingAs($user)
        ->get('/orders')
        ->assertOk()
        ->assertSee($order->user->name)
        ->assertSee($order->items->first()->product->name);
});

it('does not leak other users orders (scoping)', function () {
    $user = User::factory()->create();
    Order::factory()->for(User::factory()->create(['name' => 'User Rahasia']))->create();

    $this->actingAs($user)
        ->get('/orders')
        ->assertOk()
        ->assertDontSee('User Rahasia');
});

it('keeps query count flat regardless of order count (anti N+1)', function () {
    // 10 order milik sendiri, masing-masing 3 items + user + product relasi.
    // Tanpa eager loading: 1 + 10 + 10 + 30 ≈ 51 query.
    $user = User::factory()->create();
    Order::factory()->count(10)->for($user)->create()->each(function ($order) {
        OrderItem::factory()->count(3)->create(['order_id' => $order->id]);
    });

    DB::enableQueryLog();
    $this->actingAs($user)->get('/orders')->assertOk();
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Eager: orders + users + items + products = 4 query.
    expect($queryCount)->toBeLessThan(10);
});
