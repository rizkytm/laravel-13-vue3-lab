<?php

namespace App\Rules;

use App\Models\Product;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Custom rule object (bukan closure inline) — bisa dipakai ulang
 * dan di-unit-test sendiri.
 */
class StockAvailable implements ValidationRule
{
    public function __construct(private readonly Product $product)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ((int) $value > $this->product->stock) {
            $fail("Stok tidak mencukupi — tersisa {$this->product->stock}.");
        }
    }
}
