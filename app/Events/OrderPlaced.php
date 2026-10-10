<?php

namespace App\Events;

use App\Models\Order;

/**
 * Domain event: "order baru saja dibuat".
 * Dipakai event() setelah checkout commit — listener otomatis ditemukan
 * Laravel (auto-discovery: handle(OrderPlaced $event) di app/Listeners,
 * tanpa registrasi manual di EventServiceProvider).
 */
class OrderPlaced
{
    public function __construct(public Order $order)
    {
    }
}
