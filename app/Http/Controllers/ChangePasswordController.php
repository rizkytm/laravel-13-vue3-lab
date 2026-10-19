<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Ubah password saat login (ASVS 6.2.2 — sebelumnya hanya reset).
 * Dipakai SPA lewat proxy Vite (POST /change-password, Accept: json).
 */
class ChangePasswordController extends Controller
{
    public function store(ChangePasswordRequest $request): JsonResponse
    {
        // Cast 'hashed' di User → assignment langsung meng-hash
        $request->user()->update(['password' => $request->string('password')->toString()]);

        // Regenerate session id pasca-ubah kredensial (session fixation —
        // id yang mungkin bocor sebelum perubahan jadi tak berlaku)
        $request->session()->regenerate();

        // ASVS 16.3.1: event auth diaudit — payload aman, TANPA password
        Log::info('auth.password_changed', [
            'user_id' => $request->user()->getKey(),
            'email' => $request->user()->email,
            'ip' => $request->ip(),
        ]);

        return response()->json(['message' => 'Password berhasil diperbarui.']);
    }
}
