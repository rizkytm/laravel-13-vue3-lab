<?php

use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Middleware\EnsureCartNotEmpty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public: login → personal access token (M3)
Route::post('/token', [TokenController::class, 'store'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->get('/user', fn (Request $request) => $request->user());

// M4 — API versioned.
// name prefix 'v1.' mencegah tabrakan nama route dengan web
// (web sudah punya 'products.index', 'orders.index', dst).
Route::prefix('v1')->name('v1.')->middleware('auth:sanctum')->group(function () {
    Route::apiResource('products', ProductController::class)->only(['index', 'show'])
        // show by slug — konsisten dengan web ({product:slug}) & SPA (route params.slug)
        ->parameters(['products' => 'product:slug']);
    Route::apiResource('orders', OrderController::class)->only(['index', 'show']);

    // Tahap 3 — keranjang server-side (per user). Server otoritatif:
    // quantity di-clamp ke stok; checkout membaca tabel ini.
    Route::get('cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('cart/items', [CartController::class, 'store'])->name('cart.items.store');
    Route::patch('cart/items/{product}', [CartController::class, 'update'])->name('cart.items.update');
    Route::delete('cart/items/{product}', [CartController::class, 'destroy'])->name('cart.items.destroy');
    Route::delete('cart', [CartController::class, 'clear'])->name('cart.clear');

    // throttle:checkout → RateLimiter 'checkout' (10/menit per user) —
    // proteksi gateway dari burst order
    Route::post('checkout', [CheckoutController::class, 'store'])
        ->middleware([EnsureCartNotEmpty::class, 'throttle:checkout'])
        ->name('checkout.store');
});
