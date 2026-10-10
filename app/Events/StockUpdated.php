<?php

namespace App\Events;

use App\Models\Product;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/**
 * Real-time: stok berubah → broadcast ke channel 'products'.
 * Driver LOG (BROADCAST_CONNECTION=log) — payload tertulis ke laravel.log,
 * konsep sama dengan driver Reverb/pusher, hanya tujuannya beda.
 *
 * ShouldBroadcast → di-QUEUE, bukan dikirim inline di request.
 */
class StockUpdated implements ShouldBroadcast
{
    public function __construct(public Product $product) {}

    public function broadcastOn(): array
    {
        return [new Channel('products')]; // channel publik — tanpa auth
    }

    /**
     * Payload broadcast — daftarkan eksplisit field yang boleh keluar.
     * Model PENUH tidak ikut (hindari bocor data tak terduga).
     */
    public function broadcastWith(): array
    {
        return [
            'product_id' => $this->product->id,
            'stock' => $this->product->stock,
        ];
    }
}
