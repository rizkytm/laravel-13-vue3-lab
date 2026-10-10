<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gatekeeping payload SEBELUM masuk FormRequest/controller:
 * checkout tanpa items = permintaan tidak valid, tolak di pintu.
 * (Validasi detail per item tetap di CheckoutRequest + transaksi.)
 */
class EnsureCartNotEmpty
{
    public function handle(Request $request, Closure $next): Response
    {
        $items = $request->input('items');

        if (! is_array($items) || $items === []) {
            return response()->json([
                'message' => 'Keranjang kosong.',
                'errors' => ['items' => ['Keranjang tidak boleh kosong.']],
            ], 422);
        }

        return $next($request);
    }
}
