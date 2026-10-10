<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

// M1 — Container binding + collections
Route::get('/', [CheckoutController::class, 'create'])->name('checkout.create');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

// M2 — Eloquent (publik)
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('products.show');
Route::post('/products/{product:slug}/buy', [ProductController::class, 'buy'])->name('products.buy');

// M3 — Auth
Route::get('/login', [LoginController::class, 'create'])->name('login');
// throttle:login → RateLimiter 'login' (5/menit per email+ip) di AppServiceProvider
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout')->middleware('auth');

// M3 — Order dilindungi middleware auth:
// guest → redirect ke route('login') oleh middleware bawaan Authenticate
Route::middleware('auth')->group(function () {
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
});
