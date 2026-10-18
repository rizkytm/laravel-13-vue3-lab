<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ResetPasswordController extends Controller
{
    /**
     * Proses reset: broker memvalidasi token (cocok + belum kedaluwarsa),
     * lalu callback mengganti password. Token dihapus otomatis setelah dipakai
     * → tautan tidak bisa dipakai dua kali.
     */
    public function store(ResetPasswordRequest $request): JsonResponse|RedirectResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'token'),
            function (User $user, string $password) {
                // 'hashed' cast mengurus hashing; rotate remember token
                // agar sesi "ingat saya" lama tidak berlaku lagi
                $user->password = $password;
                $user->setRememberToken(Str::random(60));
                $user->save();

                // Reset = kepemilikan akun terbukti → langsung login
                Auth::login($user);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            $request->session()->regenerate();

            return redirect()->to(config('app.spa_url'));
        }

        return response()->json([
            'message' => 'Token reset tidak valid atau sudah kedaluwarsa.',
        ], 422);
    }
}
