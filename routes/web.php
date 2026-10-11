<?php

use App\Http\Controllers\LoginController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

// Halaman web yang tersisa = demo fitur framework yang TIDAK dicakup SPA:
// - login/logout (session auth; juga dipakai SPA via proxy)
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

// M7 — invoice TANPA auth: otorisasi = signature valid (middleware 'signed').
// Link temporarySignedRoute (30 menit) ada di OrderResource API (invoice_url).
Route::get('/orders/{order}/invoice', [OrderController::class, 'invoice'])
    ->middleware('signed')
    ->name('orders.invoice');
