<?php

use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Middleware\EnsureCartNotEmpty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public: login → personal access token (M3)
Route::post('/token', [TokenController::class, 'store'])->middleware('throttle:login');

// Webhook pembayaran (server-ke-server) — TANPA auth session & TANPA CSRF
// (grup api tidak pakai VerifyCsrfToken). Keamanan = signature diverifikasi
// di controller. Rate limit longgar: Midtrans retry berkali-kali itu normal.
Route::post('/payments/midtrans/webhook', PaymentWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('payments.midtrans.webhook');

Route::middleware('auth:sanctum')->get('/user', fn (Request $request) => $request->user());

// M4 — API versioned.
// name prefix 'v1.' mencegah tabrakan nama route dengan web
// (web sudah punya 'products.index', 'orders.index', dst).
// Semua endpoint v1 di-balik auth + throttle UMUM 120/menit per user
// (ASVS 2.4.1). Rate limit spesifik — checkout (10), token (5) — tetap
// berlaku dan lebih ketat.
Route::prefix('v1')->name('v1.')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::apiResource('products', ProductController::class)->only(['index', 'show'])
        // show by slug — konsisten dengan web ({product:slug}) & SPA (route params.slug)
        ->parameters(['products' => 'product:slug']);
    Route::apiResource('orders', OrderController::class)->only(['index', 'show']);
    // Tahap 8 — customer batalkan order pending (status machine + restock)
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])
        ->name('orders.cancel');

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
