<?php

use App\Models\User;

// Sanctum: personal access token untuk API stateless.

it('issues token with valid credentials', function () {
    $user = User::factory()->create();

    $this->postJson('/api/token', ['email' => $user->email, 'password' => 'password'])
        ->assertOk()
        ->assertJsonStructure(['token']);
});

it('rejects invalid credentials with 401 JSON', function () {
    $user = User::factory()->create();

    $this->postJson('/api/token', ['email' => $user->email, 'password' => 'salah'])
        ->assertUnauthorized()
        ->assertJsonStructure(['message']);
});

it('returns authenticated user from bearer token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-device')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJson(['email' => $user->email]);
});

it('rejects API request without token', function () {
    $this->getJson('/api/user')->assertUnauthorized();
});
