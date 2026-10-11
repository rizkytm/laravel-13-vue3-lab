<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

// Eloquent query + relasi lewat API — halaman web orders kini ditangani SPA.
// Anti N+1 tetap dijaga: eager loading → query count datar berapa pun ordernya.

it('lists own orders with user and items via api', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();
    OrderItem::factory()->count(2)->create(['order_id' => $order->id]);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/orders')
        ->assertOk()
        ->assertJsonPath('data.0.user.name', $user->name)
        ->assertJsonCount(2, 'data.0.items');
});

it('keeps query count flat regardless of order count (anti N+1)', function () {
    // 10 order milik sendiri, masing-masing 3 items + user + product relasi.
    // Tanpa eager loading: 1 + 10 + 10 + 30 ≈ 51 query.
    $user = User::factory()->create();
    Order::factory()->count(10)->for($user)->create()->each(function ($order) {
        OrderItem::factory()->count(3)->create(['order_id' => $order->id]);
    });
    Sanctum::actingAs($user);

    DB::enableQueryLog();
    $this->getJson('/api/v1/orders?per_page=50')->assertOk();
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Eager: orders + users + items + products = 4 query (+ auth session).
    expect($queryCount)->toBeLessThan(10);
});

it('does not leak other users orders (scoping)', function () {
    $user = User::factory()->create();
    Order::factory()->for(User::factory()->create(['name' => 'User Rahasia']))->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/orders')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
