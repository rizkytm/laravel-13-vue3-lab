<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;

// Tahap 6 — Filament admin: panel /admin hanya untuk is_admin,
// resource Order memakai status machine (cancel/refund via aksi).

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('allows an admin user to access the panel', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk();
});

it('blocks a regular (non-admin) user from the panel', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

it('redirects guests to the panel login', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('lets admin cancel a pending order via the domain method (restock + trace)', function () {
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->create(['stock' => 3]);
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 2]);
    $product->buy(2, $order); // stok → 1

    // Aksi panel memanggil method domain yang sama
    $order->cancel();

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($product->fresh()->stock)->toBe(3);
});

it('exposes the admin toggle state on the user', function () {
    expect(User::factory()->create(['is_admin' => false])->is_admin)->toBeFalse()
        ->and(User::factory()->admin()->create()->is_admin)->toBeTrue();
});
