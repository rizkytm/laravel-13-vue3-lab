<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// ASVS 6.2.2 — ubah password saat login. Tiap test pakai user SENDIRI
// karena limiter 'change-password' 5/menit by user id.

it('updates the password and keeps the user logged in', function () {
    $user = User::factory()->create(['password' => 'password-lama']);

    $this->actingAs($user)
        ->postJson('/change-password', [
            'current_password' => 'password-lama',
            'password' => 'password-baru-9',
            'password_confirmation' => 'password-baru-9',
        ])
        ->assertOk()
        ->assertJson(['message' => 'Password berhasil diperbarui.']);

    $user->refresh();
    expect(Hash::check('password-baru-9', $user->password))->toBeTrue()
        ->and(Hash::check('password-lama', $user->password))->toBeFalse();
});

it('rejects wrong current password and keeps the old one working', function () {
    $user = User::factory()->create(['password' => 'password-lama']);

    $this->actingAs($user)
        ->postJson('/change-password', [
            'current_password' => 'salah-banget',
            'password' => 'password-baru-9',
            'password_confirmation' => 'password-baru-9',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');

    // Password lama TETAP berlaku — perubahan gagal total
    expect(Hash::check('password-lama', $user->refresh()->password))->toBeTrue();
});

it('validates the new password policy (min 8, konfirmasi, beda dari lama)', function () {
    $payload = fn (string $password, string $confirm) => [
        'current_password' => 'password-lama',
        'password' => $password,
        'password_confirmation' => $confirm,
    ];

    // terlalu pendek
    $this->actingAs(User::factory()->create(['password' => 'password-lama']))
        ->postJson('/change-password', $payload('abc1234', 'abc1234'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    // konfirmasi tidak cocok
    $this->actingAs(User::factory()->create(['password' => 'password-lama']))
        ->postJson('/change-password', $payload('password-baru-9', 'beda-lho-99'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    // password baru = password lama → ditolak
    $this->actingAs(User::factory()->create(['password' => 'password-lama']))
        ->postJson('/change-password', $payload('password-lama', 'password-lama'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');
});

it('requires authentication', function () {
    $this->postJson('/change-password', [
        'current_password' => 'x',
        'password' => 'password-baru-9',
        'password_confirmation' => 'password-baru-9',
    ])->assertUnauthorized();
});

it('rate limits to 5 attempts per minute per user', function () {
    $user = User::factory()->create(['password' => 'password-lama']);
    $payload = [
        'current_password' => 'salah',
        'password' => 'password-baru-9',
        'password_confirmation' => 'password-baru-9',
    ];

    // 5 kali pertama: kena validasi (422) — limiter belum memblokir
    foreach (range(1, 5) as $i) {
        $this->actingAs($user)->postJson('/change-password', $payload)
            ->assertUnprocessable();
    }

    // Percobaan ke-6 dalam menit yang sama → 429
    $this->actingAs($user)->postJson('/change-password', $payload)->assertStatus(429);
});
