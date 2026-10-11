<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;

// Smoke test dengan data ASLI dari seeder (bukan factory per test) —
// memastikan DatabaseSeeder tetap valid tiap kali schema berubah.

it('boots from seed data and serves main pages', function () {
    $this->seed();

    // Order::factory punya user_id User::factory() → 5 user tambahan
    // selain test@example.com (kontrak seeder: 12 product, 5 order)
    expect(User::count())->toBeGreaterThanOrEqual(1)
        ->and(Product::count())->toBe(12)
        ->and(Order::count())->toBe(5)
        ->and(OrderItem::count())->toBeGreaterThan(0); // 1-3 item per order

    $this->get('/login')->assertOk();
    $this->get('/products/'.Product::first()->slug)
        ->assertOk()
        ->assertSee(Product::first()->name);
});
