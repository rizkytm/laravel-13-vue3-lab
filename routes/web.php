<?php

use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\VerifyEmailController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Halaman web yang tersisa = demo fitur framework yang TIDAK dicakup SPA:
// - login/logout/register/reset (session auth; juga dipakai SPA via proxy)
// - products.show (demo beli lockForUpdate + upload gambar disk privat)
// - orders.invoice (demo SIGNED URL tanpa auth)
// Checkout, daftar produk, dan daftar/order detail kini ditangani Vue 3 + API v1.

// M2 — Eloquent (halaman demo produk: beli + upload)
Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('products.show');
Route::post('/products/{product:slug}/buy', [ProductController::class, 'buy'])->name('products.buy');

// M6 — Upload & tampil gambar (disk privat, lewat controller)
Route::post('/products/{product:slug}/image', [ProductController::class, 'uploadImage'])->name('products.image.store');
Route::get('/products/{product:slug}/image', [ProductController::class, 'image'])->name('products.image');

// M3 — Auth
Route::get('/login', [LoginController::class, 'create'])->name('login');
// throttle:login → RateLimiter 'login' (5/menit per email+ip) di AppServiceProvider
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout')->middleware('auth');

// Tahap 1 — Register + password reset + verifikasi email (semua session auth,
// dipakai SPA lewat proxy Vite seperti /login)
Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:register');

// throttle:password → 3/menit per email+ip — cegah banjir email reset
Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->middleware('throttle:password');
Route::post('/reset-password', [ResetPasswordController::class, 'store'])->middleware('throttle:password');
// Route GENERATOR tautan di email reset (Laravel cari nama 'password.reset').
// Kalau dibuka langsung (prod, tanpa SPA fallback) teruskan ke SPA form reset.
Route::get('/reset-password', fn (Request $request) => redirect(
    config('app.spa_url').'/reset-password?'.http_build_query($request->query())
))->name('password.reset');

// Verifikasi email: link dari email → route SIGNED (signature di-hash, tidak
// bisa dipalsukan) + auth. Idempotent, klik dua kali bukan error.
Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['auth', 'signed'])
    ->name('verification.verify');
Route::post('/email/verification-notification', [VerifyEmailController::class, 'resend'])
    ->middleware(['auth', 'throttle:5,1'])
    ->name('verification.resend');

// M7 — invoice TANPA auth: otorisasi = signature valid (middleware 'signed').
// Link temporarySignedRoute (30 menit) ada di OrderResource API (invoice_url).
Route::get('/orders/{order}/invoice', [OrderController::class, 'invoice'])
    ->middleware('signed')
    ->name('orders.invoice');
