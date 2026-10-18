<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    /**
     * Tautan verifikasi dari email: route SIGNED (middleware 'signed')
     * sehingga hash-nya tidak bisa dipalsukan, + 'auth' karena targetnya
     * akun yang sedang login.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        // hash = sha1(email) — dibandingkan dengan hash_equals (constant-time)
        if (! hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash'))) {
            abort(403);
        }

        // Idempotent: klik link dua kali bukan error
        if (! $user->hasVerifiedEmail()) {
            // markEmailAsVerified: set email_verified_at + fire EmailVerified event
            $user->markEmailAsVerified();
        }

        return redirect()->to(config('app.spa_url').'/verify-email?status=verified');
    }

    /**
     * Kirim ulang tautan verifikasi (tombol di halaman verify-email SPA).
     */
    public function resend(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email sudah terverifikasi.'], 422);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'Tautan verifikasi baru sudah dikirim.'], 202);
    }
}
