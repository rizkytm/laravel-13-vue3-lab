<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForgotPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    /**
     * Minta tautan reset password. Password broker (config/auth.php:
     * users.passwords, expire 60 menit) yang membuat token — di DB token
     * disimpan TERHASH, yang dikirim email hanya nilai mentahnya.
     */
    public function store(ForgotPasswordRequest $request): JsonResponse
    {
        // sendResetLink: create token → kirim ResetPassword notification.
        // Status sengaja DIABAIKAN — respons selalu generik agar tidak
        // membocorkan email terdaftar atau tidak (user enumeration).
        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => 'Jika email terdaftar, tautan reset password sudah dikirim.',
        ], 202);
    }
}
