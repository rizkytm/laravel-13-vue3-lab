<?php

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

it('lists orders with user and items rendered', function () {
    $order = Order::factory()->create();
    OrderItem::factory()->count(2)->create(['order_id' => $order->id]);

    $this->get('/orders')
        ->assertOk()
        ->assertSee($order->user->name)
        ->assertSee($order->items->first()->product->name);
});

it('keeps query count flat regardless of order count (anti N+1)', function () {
    // 10 order, masing-masing punya user + 3 items → kalau N+1, butuh 10+30+ query
    Order::factory()->count(10)->create()->each(function ($order) {
        OrderItem::factory()->count(3)->create(['order_id' => $order->id]);
    });

    DB::enableQueryLog();
    $this->get('/orders')->assertOk();
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Eager loading: orders + users + items + products = 4 query.
    // Threshold 10 masih jauh di bawah pola N+1 (>30), tapi memberi ruang
    // untuk query tambahan yang wajar tanpa membuat test rapuh.
    expect($queryCount)->toBeLessThan(10);
});
