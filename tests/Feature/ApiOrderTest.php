<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('requires token (401 for guest)', function () {
    $this->getJson('/api/v1/orders')->assertUnauthorized();
});

it('lists own orders with cursor pagination', function () {
    $user = User::factory()->create();
    Order::factory()->count(12)->for($user)->create();
    Sanctum::actingAs($user);

    $page1 = $this->getJson('/api/v1/orders?per_page=5')->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonStructure(['data' => ['*' => ['id', 'status', 'total', 'user', 'items']]]);

    $nextCursor = $page1->json('meta.next_cursor');
    expect($nextCursor)->not->toBeNull();

    $page2 = $this->getJson("/api/v1/orders?per_page=5&cursor={$nextCursor}")->assertOk()
        ->assertJsonCount(5, 'data');

    // Cursor = token posisi: tidak ada duplikat antar halaman,
    // dan insert data baru di tengah tidak menggeser halaman seperti offset.
    $ids1 = array_column($page1->json('data'), 'id');
    $ids2 = array_column($page2->json('data'), 'id');
    expect(array_intersect($ids1, $ids2))->toBeEmpty();
});

it('does not list other users orders', function () {
    $user = User::factory()->create();
    Order::factory()->for(User::factory()->create(['name' => 'User Rahasia']))->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/orders')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('shows own order with items via policy', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();
    OrderItem::factory()->count(2)->create(['order_id' => $order->id]);
    Sanctum::actingAs($user);

    $this->getJson("/api/v1/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.items_count', 2);
});

it('forbids other user from viewing the order (403 JSON)', function () {
    $order = Order::factory()->create();
    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/v1/orders/{$order->id}")->assertForbidden();
});
