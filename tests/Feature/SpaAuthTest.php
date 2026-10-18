<?php

use App\Models\User;

// Sanctum SPA: autentikasi API via SESSION COOKIE (stateful),
// bukan Bearer token — dipakai frontend Vue di localhost:5173.

it('authenticates api request via session cookie after login', function () {
    // 1. Ambil CSRF cookie (alur SPA asli: GET /sanctum/csrf-cookie dulu).
    //    Catatan: di test, VerifyCsrfToken OTOMATIS skip (runningUnitTests)
    //    — jadi header X-XSRF-TOKEN tidak diperlukan di sini.
    $this->get('/sanctum/csrf-cookie')->assertCookie('XSRF-TOKEN');

    // 2. Login lewat endpoint WEB (session auth)
    $user = User::factory()->create();
    $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(config('app.spa_url'));
    $this->assertAuthenticatedAs($user);

    // 3. Request API sekarang ter-auth via session cookie (auth:sanctum
    //    fallback ke web guard untuk request stateful) — TANPA Bearer token
    $this->getJson('/api/v1/orders')
        ->assertOk()
        ->assertJsonCount(0, 'data'); // user belum punya order — bukti 200, bukan 401
});

// Regresi bug 2026-10-18: login 302 sukses tapi SPA selalu tampil
// "Email atau password salah." — /api/user balik 401.
// Akar: origin Vite dev (:5173) tidak ada di sanctum.stateful →
// EnsureFrontendRequestsAreStateful menganggap request bukan dari frontend →
// StartSession tidak jalan di /api/* → cookie session diabaikan.
//
// Di-test feature assertion-nya harus ke CONFIG: instance app dibagi antar
// request dalam satu test, jadi guard tetap menganggap user login meski
// middleware session tidak pernah jalan (test lolos = palsu).
it('lists the Vite dev origin as a stateful Sanctum domain', function () {
    $stateful = config('sanctum.stateful');

    expect($stateful)->toContain('localhost:5173')
        ->and($stateful)->toContain('127.0.0.1:5173');
});

it('still rejects api request without any credentials', function () {
    $this->getJson('/api/v1/orders')->assertUnauthorized();
});

it('keeps bearer token flow working alongside cookie flow', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/orders')->assertOk();
});
