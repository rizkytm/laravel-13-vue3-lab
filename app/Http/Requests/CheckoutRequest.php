<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        // auth:sanctum di route sudah menjamin login — ini lapisan kedua
        // (defense in depth): kalau route keliru tanpa auth, tetap 403.
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            // Cek stok cukup TIDAK di sini: stok bisa berubah antar validasi
            // dan eksekusi — penjagaan dilakukan atomik di dalam transaksi
            // (Product::buy + lockForUpdate). Validasi = UX, lock = kebenaran.
        ];
    }
}
