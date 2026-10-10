<?php

use App\Models\Order;
use App\Models\User;

// Localization: ?lang= → session → trans() + tanggal Carbon.

it('switches locale by query and persists in session', function () {
    $this->get('/products?lang=id')->assertOk()->assertSee('Daftar Produk');

    // Pilihan tersimpan di session — request berikut tanpa param tetap Indonesia
    $this->get('/products')->assertSee('Daftar Produk');

    $this->get('/products?lang=en')->assertSee('Product List');
});

it('renders month names in Indonesian locale', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();
    Order::query()->whereKey($order->id)->update(['created_at' => '2026-10-10 12:00:00']);

    $this->actingAs($user)
        ->get("/orders/{$order->id}?lang=id")
        ->assertOk()
        ->assertSee('Dibuat')
        ->assertSee('Oktober 2026'); // Carbon translation, bukan "October"
});
