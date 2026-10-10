<?php

use App\Http\Controllers\Api\TokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// M3 — Sanctum personal access token (stateless API auth).

// Login → token. throttle:login dipakai ulang (5/menit per email+ip)
Route::post('/token', [TokenController::class, 'store'])->middleware('throttle:login');

// Endpoint yang dijaga token — 401 JSON kalau tanpa/gagal token
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
