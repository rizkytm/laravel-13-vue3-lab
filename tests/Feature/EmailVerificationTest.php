<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

// Tahap 1 — Email verification: link SIGNED + auth, idempotent,
// kirim ulang dengan rate limit.

/** URL signed verifikasi — sama dengan yang dibangun framework. */
function verificationUrl(User $user, ?DateTimeInterface $expires = null): string
{
    return URL::temporarySignedRoute('verification.verify', $expires ?? now()->addMinutes(10), [
        'id' => $user->id,
        'hash' => sha1($user->getEmailForVerification()),
    ]);
}

it('marks the email verified via the signed link and redirects to SPA', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(verificationUrl($user))
        ->assertRedirect(config('app.spa_url').'/verify-email?status=verified');

    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

it('is idempotent — clicking the link twice is not an error', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(verificationUrl($user))->assertRedirect();
    $this->actingAs($user)->get(verificationUrl($user))->assertRedirect();

    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

it('rejects a link with a tampered hash', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), [
        'id' => $user->id,
        'hash' => sha1('email-lain@example.com'), // hash tidak cocok
    ]);

    $this->actingAs($user)->get($url)->assertForbidden();

    expect($user->fresh()->email_verified_at)->toBeNull();
});

it('rejects an expired signed link', function () {
    $user = User::factory()->unverified()->create();

    $url = verificationUrl($user, now()->subMinutes(15));

    $this->actingAs($user)->get($url)->assertForbidden();

    expect($user->fresh()->email_verified_at)->toBeNull();
});

it('redirects guests to login instead of verifying', function () {
    $user = User::factory()->unverified()->create();

    $this->get(verificationUrl($user))->assertRedirect(route('login'));

    expect($user->fresh()->email_verified_at)->toBeNull();
});

it('resends the verification email on request', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->postJson('/email/verification-notification')
        ->assertStatus(202)
        ->assertJson(['message' => 'Tautan verifikasi baru sudah dikirim.']);

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('rejects resend when already verified', function () {
    Notification::fake();
    $user = User::factory()->create(); // factory default: sudah verified

    $this->actingAs($user)->postJson('/email/verification-notification')
        ->assertStatus(422)
        ->assertJson(['message' => 'Email sudah terverifikasi.']);

    Notification::assertNothingSent();
});

it('requires auth for resend', function () {
    $this->postJson('/email/verification-notification')->assertUnauthorized();
});

it('throttles repeated resend requests with 429', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user);
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/email/verification-notification')->assertStatus(202);
    }

    // throttle:5,1 → percobaan ke-6 dalam semenit diblokir
    $this->postJson('/email/verification-notification')->assertStatus(429);
});
