<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class TokenController extends Controller
{
    /**
     * Login → personal access token.
     *
     * Kenapa bukan Auth::attempt()? Session guard butuh session store —
     * route API stateless (tanpa middleware session). Jadi validasi
     * credential manual dengan Hash::check.
     *
     * Token: plaintext hanya dikembalikan SEKALI di response ini.
     * DB hanya menyimpan hash-nya (table personal_access_tokens).
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            return response()->json(['message' => 'Email atau password salah.'], 401);
        }

        $token = $user->createToken('m3-api')->plainTextToken;

        return response()->json(['token' => $token]);
    }
}
