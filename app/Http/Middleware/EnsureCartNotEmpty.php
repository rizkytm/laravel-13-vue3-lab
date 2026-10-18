<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gatekeeping SEBELUM masuk controller: checkout dengan keranjang kosong
 * = permintaan tidak valid, tolak di pintu. Pengecekan terhadap cart
 * SERVER-SIDE (tabel cart_items) — bukan payload, yang kini tidak lagi
 * membawa items.
 * (Validasi detail per item + lock stok tetap di transaksi checkout.)
 */
class EnsureCartNotEmpty
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()->cartItems()->doesntExist()) {
            return response()->json([
                'message' => 'Keranjang kosong.',
                'errors' => ['items' => ['Keranjang tidak boleh kosong.']],
            ], 422);
        }

        return $next($request);
    }
}
