<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;

// Signed URL: otorisasi tanpa login via signature HMAC + expiry.

it('renders invoice as guest with valid signed url', function () {
    $order = Order::factory()->create();
    $url = URL::temporarySignedRoute('orders.invoice', now()->addMinutes(30), $order);

    $this->get($url)
        ->assertOk()
        ->assertSee("Invoice Order #{$order->id}");
});

it('rejects unsigned request (403)', function () {
    $order = Order::factory()->create();

    $this->get("/orders/{$order->id}/invoice")->assertForbidden();
});

it('rejects tampered signature (403)', function () {
    $order = Order::factory()->create();
    $url = URL::temporarySignedRoute('orders.invoice', now()->addMinutes(30), $order);

    // Tambah param setelah link dibuat → signature gak cocok lagi
    $this->get($url.'&extra=1')->assertForbidden();
});

it('rejects expired link (403)', function () {
    $order = Order::factory()->create();
    $url = URL::temporarySignedRoute('orders.invoice', now()->subMinute(), $order);

    $this->get($url)->assertForbidden();
});

it('generates signed link via api order detail (invoice_url)', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();
    Sanctum::actingAs($user);

    $url = $this->getJson("/api/v1/orders/{$order->id}")
        ->assertOk()
        ->json('data.invoice_url');

    expect($url)->toContain('signature=');

    // Link yang dihasilkan benar-benar bisa dibuka (guest, tanpa auth)
    $this->get($url)->assertOk()->assertSee("Invoice Order #{$order->id}");
});
