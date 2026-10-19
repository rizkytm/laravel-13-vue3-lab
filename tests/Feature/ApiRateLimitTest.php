<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

// ASVS 2.4.1 — throttle umum API v1: 120/menit per user.

it('allows normal api usage well under the limit', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/products?per_page=5')->assertOk();
    $this->getJson('/api/v1/orders')->assertOk();
});

it('rate limits api v1 to 120 requests per minute per user', function () {
    Sanctum::actingAs(User::factory()->create());

    // 120 pertama lolos (server memproses, mungkin 422 utk body kosong —
    // yang penting BUKAN 429)
    foreach (range(1, 120) as $i) {
        $response = $this->getJson('/api/v1/orders');
        $this->assertNotSame(429, $response->getStatusCode(), "request #{$i} seharusnya belum kena limit");
    }

    // Percobaan ke-121 dalam menit sama → 429
    $this->getJson('/api/v1/orders')->assertStatus(429);
});

it('keys the limit per user — user lain tidak terdampak', function () {
    Sanctum::actingAs(User::factory()->create());

    foreach (range(1, 121) as $i) {
        $this->getJson('/api/v1/orders');
    }

    // User berikutnya tetap dilayani (bucket rate limiter terpisah)
    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/orders')->assertOk();
});
