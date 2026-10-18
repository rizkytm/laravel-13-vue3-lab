<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

// Tahap 1 — Register: buat akun + auto-login + kirim email verifikasi.
// Verifikasi email ada di EmailVerificationTest.

it('registers a user, logs them in, and sends verification email', function () {
    Notification::fake();

    $this->post('/register', [
        'name' => 'Rizky',
        'email' => 'rizky@example.com',
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
    ])->assertRedirect(config('app.spa_url'));

    $user = User::query()->where('email', 'rizky@example.com')->firstOrFail();
    expect($user->name)->toBe('Rizky')
        ->and($user->email_verified_at)->toBeNull(); // belum verifikasi sampai link diklik

    $this->assertAuthenticatedAs($user);

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('hashes the password (never stores plaintext)', function () {
    $this->post('/register', [
        'name' => 'Rizky',
        'email' => 'hash@example.com',
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
    ]);

    $user = User::query()->where('email', 'hash@example.com')->firstOrFail();
    expect($user->password)->not->toBe('rahasia123')
        ->and(Hash::check('rahasia123', $user->password))->toBeTrue();
});

it('rejects duplicate email with validation error', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/register', [
        'name' => 'Rizky',
        'email' => 'taken@example.com',
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
    ])->assertStatus(422)->assertJsonValidationErrors(['email' => 'Email sudah terdaftar.']);

    $this->assertGuest();
});

it('validates registration fields with Indonesian messages', function () {
    $this->postJson('/register', [
        'name' => '',
        'email' => 'bukan-email',
        'password' => 'pendek',
        'password_confirmation' => 'beda',
    ])->assertStatus(422)
        ->assertJsonValidationErrors([
            'name' => 'Nama wajib diisi.',
            'email' => 'Format email tidak valid.',
            'password' => 'Konfirmasi password tidak cocok.',
        ]);
});

it('throttles repeated registration attempts with 429', function () {
    $payload = fn (int $i) => [
        'name' => "User {$i}",
        'email' => "user{$i}@example.com",
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
    ];

    foreach (range(1, 3) as $i) {
        $this->post('/register', $payload($i))->assertRedirect(config('app.spa_url'));
    }

    // RateLimiter 'register': 3/menit per IP → percobaan ke-4 diblokir
    $this->post('/register', $payload(4))->assertStatus(429);
});
