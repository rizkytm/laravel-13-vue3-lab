<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

// Tahap 1 — Password reset: token hashed di DB, expiry 60 menit,
// tautan sekali pakai, respons generik (anti user-enumeration).

it('responds generically and sends reset link for a known email', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->postJson('/forgot-password', ['email' => $user->email])
        ->assertStatus(202)
        ->assertJson(['message' => 'Jika email terdaftar, tautan reset password sudah dikirim.']);

    Notification::assertSentTo($user, ResetPassword::class);
});

it('responds the same generically for an unknown email (no user enumeration)', function () {
    Notification::fake();

    $this->postJson('/forgot-password', ['email' => 'tidak-ada@example.com'])
        ->assertStatus(202)
        ->assertJson(['message' => 'Jika email terdaftar, tautan reset password sudah dikirim.']);

    Notification::assertNothingSent();
});

it('stores the reset token hashed in the database', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->postJson('/forgot-password', ['email' => $user->email])->assertStatus(202);

    $row = DB::table('password_reset_tokens')->where('email', $user->email)->first();
    expect($row)->not->toBeNull();

    // Ambil token mentah dari notification, bandingkan dengan yang di DB
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use ($row) {
        return Hash::check($n->token, $row->token);
    });
});

it('resets the password with a valid token and logs the user in', function () {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'barusaja123',
        'password_confirmation' => 'barusaja123',
    ])->assertRedirect(config('app.spa_url'));

    $this->assertAuthenticatedAs($user);
    expect(Hash::check('barusaja123', $user->fresh()->password))->toBeTrue();

    // Token terhapus setelah dipakai → tautan tidak bisa dipakai dua kali
    expect(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse();
});

it('rejects an invalid token', function () {
    $user = User::factory()->create();

    $this->postJson('/reset-password', [
        'token' => 'salah-satu',
        'email' => $user->email,
        'password' => 'barusaja123',
        'password_confirmation' => 'barusaja123',
    ])->assertStatus(422)
        ->assertJson(['message' => 'Token reset tidak valid atau sudah kedaluwarsa.']);

    $this->assertGuest();
});

it('rejects an expired token (older than 60 minutes)', function () {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    // Age the token beyond the broker's 60-minute expiry
    DB::table('password_reset_tokens')->where('email', $user->email)
        ->update(['created_at' => now()->subMinutes(120)]);

    $this->postJson('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'barusaja123',
        'password_confirmation' => 'barusaja123',
    ])->assertStatus(422)
        ->assertJson(['message' => 'Token reset tidak valid atau sudah kedaluwarsa.']);
});

it('validates reset fields with Indonesian messages', function () {
    $this->postJson('/reset-password', [
        'token' => '',
        'email' => 'bukan-email',
        'password' => 'pendek',
        'password_confirmation' => 'beda',
    ])->assertStatus(422)
        ->assertJsonValidationErrors([
            'token' => 'Token wajib diisi.',
            'email' => 'Format email tidak valid.',
            'password' => 'Konfirmasi password tidak cocok.',
        ]);
});

it('throttles repeated forgot-password attempts with 429', function () {
    $user = User::factory()->create();

    foreach (range(1, 3) as $attempt) {
        $this->postJson('/forgot-password', ['email' => $user->email])->assertStatus(202);
    }

    // RateLimiter 'password': 3/menit per email+ip → percobaan ke-4 diblokir
    $this->postJson('/forgot-password', ['email' => $user->email])->assertStatus(429);
});

it('redirects the GET reset link from email to the SPA form', function () {
    // Nama route 'password.reset' dipakai framework untuk membangun tautan
    // di email — GET ini diteruskan ke form reset di SPA.
    $this->get('/reset-password?token=abc&email=a%40b.com')
        ->assertRedirect(config('app.spa_url').'/reset-password?token=abc&email=a%40b.com');
});
