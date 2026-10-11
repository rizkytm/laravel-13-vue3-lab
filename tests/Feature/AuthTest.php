<?php

use App\Models\User;

it('shows login form', function () {
    $this->get('/login')->assertOk()->assertSee('Login');
});

it('logs in with valid credentials and redirects to SPA', function () {
    $user = User::factory()->create(); // password bawaan factory: "password"

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(config('app.spa_url'));

    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'salah'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('redirects guest away from protected logout (auth middleware)', function () {
    $this->post('/logout')->assertRedirect(route('login'));
});

it('logs out and invalidates session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/logout')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
