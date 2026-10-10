<?php

use App\Models\User;

// Brute-force protection: RateLimiter 'login' (5/menit per email+ip).
// Cache driver array di test = reset per test, jadi gak bocor antar test.

it('throttles repeated failed logins with 429', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', ['email' => $user->email, 'password' => 'salah'])
            ->assertSessionHasErrors('email'); // 5 percobaan pertama masih diproses
    }

    // Percobaan ke-6 dalam 1 menit yang sama → diblokir
    $this->post('/login', ['email' => $user->email, 'password' => 'salah'])
        ->assertStatus(429);
});
