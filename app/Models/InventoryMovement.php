<?php

namespace App\Models;

use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak setiap perubahan stok. products.stock = kolom turunan (cache);
 * baris di sini = sumber kebenaran audit: stok TIDAK PERNAH berubah
 * tanpa meninggalkan movement (lihat InventoryService).
 */
class InventoryMovement extends Model
{
    protected $fillable = [
        'product_id', 'order_id', 'type', 'quantity', 'stock_after', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'quantity' => 'integer',   // signed delta
            'stock_after' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
