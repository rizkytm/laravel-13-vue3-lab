<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;

// ASVS 16.3.1/16.3.2 — audit event auth & penolakan otorisasi, terstruktur
// (pola StructuredLogTest: Log::spy + assert per-pesan).

it('logs failed login with email context but NEVER the password', function () {
    Log::spy();

    $user = User::factory()->create(['password' => 'password-asli']);

    $this->from('/login')->post('/login', [
        'email' => $user->email,
        'password' => 'password-salah',
    ])->assertSessionHasErrors('email');

    Log::shouldHaveReceived('warning')
        ->withArgs(function (string $message, array $context) use ($user) {
            return $message === 'auth.login_failed'
                && $context['email'] === $user->email
                && isset($context['ip'])
                // JANGAN pernah ada kredensial di log
                && ! array_key_exists('password', $context)
                && ! in_array('password-salah', $context, true);
        })->once();
});

it('logs successful login with user id', function () {
    Log::spy();

    $user = User::factory()->create(['password' => 'password-asli']);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password-asli',
    ])->assertRedirect(config('app.spa_url'));

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message, array $context) => $message === 'auth.login'
            && $context['user_id'] === $user->id
            && $context['email'] === $user->email)
        ->once();
});

it('logs logout before the session is destroyed', function () {
    Log::spy();

    $user = User::factory()->create();
    // actingAs biasa (guard web) — JANGAN Sanctum::actingAs: itu mengeset
    // default guard ke RequestGuard 'sanctum' yang tak punya logout()
    $this->actingAs($user);

    $this->post('/logout')->assertRedirect(route('login'));

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message, array $context) => $message === 'auth.logout'
            && $context['user_id'] === $user->id)
        ->once();
});

it('logs authorization denial (403) with path and user', function () {
    Log::spy();

    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $owner->id]);

    Sanctum::actingAs($intruder);
    $this->getJson("/api/v1/orders/{$order->id}")->assertForbidden();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context) => $message === 'auth.denied'
            && $context['status'] === 403
            && $context['path'] === "/api/v1/orders/{$order->id}"
            && $context['user_id'] === $intruder->id)
        ->once();
});

it('logs unauthenticated access (401) with path', function () {
    Log::spy();

    $this->getJson('/api/v1/orders')->assertUnauthorized();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context) => $message === 'auth.unauthenticated'
            && $context['status'] === 401
            && $context['path'] === '/api/v1/orders'
            && $context['user_id'] === null)
        ->once();
});
