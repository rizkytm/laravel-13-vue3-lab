<?php

namespace App\Http\Requests;

use App\Rules\StockAvailable;
use Illuminate\Foundation\Http\FormRequest;

class BuyProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // produk publik — otorisasi ownership tidak relevan di sini
    }

    public function rules(): array
    {
        return [
            // Custom rule: cek stok cukup SEBELUM masuk controller.
            // Ini lapisan cepat ( pesan jelas ke user ).
            // Penjagaan race tetap di Product::buy() dengan lockForUpdate —
            // validasi ≠ jaminan konsistensi data.
            'quantity' => [
                'required',
                'integer',
                'min:1',
                new StockAvailable($this->route('product')),
            ],
        ];
    }
}
