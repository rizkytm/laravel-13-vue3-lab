<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris keranjang server-side. localStorage cukup untuk mode tamu —
 * yang login, keranjang hidup di DB: sama di semua perangkat, tidak bisa
 * dimodifikasi langsung oleh client, dan checkout membaca dari sini.
 */
class CartItem extends Model
{
    protected $fillable = ['user_id', 'product_id', 'quantity'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
