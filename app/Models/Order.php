<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'status', 'total', 'transaction_id', 'paid_at'];

    protected function casts(): array
    {
        return [
            // Enum cast: $order->status === OrderStatus::Paid (bukan 'paid' string)
            'status' => OrderStatus::class,
            'total' => 'integer',
            'paid_at' => 'datetime', // ->toIso8601String() di resource butuh Carbon
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Gerbang SATU-SATUNYA perubahan status (status machine).
     * Transisi tidak sah → return false, status tidak disentuh.
     * $attributes menyertai update (transaction_id + paid_at saat bayar).
     */
    public function transitionTo(OrderStatus $next, array $attributes = []): bool
    {
        if (! $this->status->canTransitionTo($next)) {
            return false;
        }

        $this->update(array_merge(['status' => $next], $attributes));

        return true;
    }

    /**
     * Refund: transisi paid → refunded + KEMBALIKAN stok (jejak movement 'in').
     * Atomic — kalau restock gagal, refund ikut batal. Pemanggilan gateway
     * (pengembalian uang) ditangani RefundService, bukan model.
     */
    public function refund(string $reason = 'Order di-refund'): bool
    {
        return DB::transaction(function () use ($reason): bool {
            if (! $this->transitionTo(OrderStatus::Refunded)) {
                return false;
            }

            $inventory = app(InventoryService::class);

            foreach ($this->items()->with('product')->get() as $item) {
                $inventory->increment($item->product, $item->quantity, $reason, $this);
            }

            return true;
        });
    }

    /**
     * Batalkan order: transisi status + KEMBALIKAN stok (jejak movement 'in').
     * Atomic — kalau restock gagal, pembatalan ikut batal.
     */
    public function cancel(string $reason = 'Order dibatalkan'): bool
    {
        return DB::transaction(function () use ($reason): bool {
            if (! $this->transitionTo(OrderStatus::Cancelled)) {
                return false;
            }

            $inventory = app(InventoryService::class);

            foreach ($this->items()->with('product')->get() as $item) {
                $inventory->increment($item->product, $item->quantity, $reason, $this);
            }

            return true;
        });
    }
}
