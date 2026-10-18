<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    /**
     * Daftar akun baru → langsung login (session) → kirim email verifikasi.
     * Sengaja mengikuti pola LoginController: web route + session cookie,
     * sehingga SPA cukup csrf-cookie lalu POST /register (redirect diabaikan,
     * keberhasilan dibuktikan GET /api/user).
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        // 'hashed' cast: password di-hash otomatis — controller tidak pernah
        // menyimpan password plaintext
        $user = User::create($request->safe()->only(['name', 'email', 'password']));

        Auth::login($user);
        // regenerate: cegah session fixation (id lama tidak berlaku)
        $request->session()->regenerate();

        // Kirim tautan verifikasi (bawaan framework, channel mail → log driver)
        $user->sendEmailVerificationNotification();

        return redirect()->to(config('app.spa_url'));
    }
}
