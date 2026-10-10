<?php

use App\Models\Order;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

// Format error API yang konsisten tanpa membocorkan info internal.

it('hides internal model name in api 404', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/products/999999')
        ->assertNotFound()
        // Bawaan Laravel: "No query results for model [App\Models\Product]..."
        // → bocorkan struktur namespace ke penyerang. Sekarang: pesan generik.
        ->assertJsonPath('message', 'Resource tidak ditemukan.')
        ->assertJsonMissingPath('exception');
});

it('returns consistent json message for api 403', function () {
    $order = Order::factory()->create(); // milik user lain
    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/v1/orders/{$order->id}")
        ->assertForbidden()
        ->assertJsonPath('message', 'Anda tidak punya akses ke resource ini.');
});

it('keeps validation errors in standard shape', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/checkout', ['items' => []])
        ->assertStatus(422)
        ->assertJsonStructure(['message', 'errors']);
});
