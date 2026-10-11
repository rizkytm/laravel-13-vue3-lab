<?php

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\URL;

// Localization: ?lang= → session → trans() + tanggal Carbon.

it('switches locale by query and persists in session', function () {
    $product = Product::factory()->create();

    $this->get("/products/{$product->slug}?lang=id")
        ->assertOk()
        ->assertSee('Unggah gambar produk');

    // Pilihan tersimpan di session — request berikut tanpa param tetap Indonesia
    $this->get("/products/{$product->slug}")->assertSee('Unggah gambar produk');

    $this->get("/products/{$product->slug}?lang=en")->assertSee('Upload product image');
});

it('renders month names in Indonesian locale', function () {
    $product = Product::factory()->create();
    $order = Order::factory()->create();
    Order::query()->whereKey($order->id)->update(['created_at' => '2026-10-10 12:00:00']);

    // Set locale via session dulu — invoice = signed URL, ?lang= tambahan
    // akan merusak signature (memang begitu aturannya).
    $this->get("/products/{$product->slug}?lang=id");

    $url = URL::temporarySignedRoute('orders.invoice', now()->addMinutes(5), $order->id);

    $this->get($url)
        ->assertOk()
        ->assertSee('Oktober 2026'); // Carbon translation, bukan "October"
});
