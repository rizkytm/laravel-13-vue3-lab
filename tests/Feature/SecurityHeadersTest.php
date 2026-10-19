<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

// Hardening ASVS V3.4 (headers) + V14.3 (cache-control) — middleware global
// AddSecurityHeaders.

it('adds security headers to web responses', function () {
    $this->get('/login')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('adds security headers to api responses', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/products')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY');
});

it('adds security headers to the filament admin panel', function () {
    $response = $this->get('/admin/login');

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("frame-ancestors 'none'");
});

it('sets CSP with the script-safe directives only', function () {
    // frame-ancestors/object-src/base-uri/form-action — TANPA script-src
    // (Filament v4 inline script tanpa nonce; lihat AddSecurityHeaders).
    $this->get('/login')
        ->assertHeader('Content-Security-Policy', implode('; ', [
            "frame-ancestors 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]));
});

it('disables browser caching for dynamic responses (ASVS 14.3.2)', function () {
    $this->get('/login')->assertHeader('Cache-Control', 'no-store, private');

    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/orders')->assertHeader('Cache-Control', 'no-store, private');
});

it('keeps response headers on error responses too', function () {
    // Exception dirender jadi response — header tetap harus menempel
    // (scanner keamanan memeriksa juga halaman 404).
    $this->getJson('/api/v1/tidak-ada')
        ->assertNotFound()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY');
});

it('does not send CORS headers to unknown origins (ASVS 3.4.2)', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/products', ['Origin' => 'https://evil.example'])
        ->assertOk()
        ->assertHeaderMissing('Access-Control-Allow-Origin');

    $this->getJson('/api/v1/products', ['Origin' => 'http://localhost:5173'])
        ->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
});
