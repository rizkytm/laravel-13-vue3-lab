<?php

namespace App\Services\Orders;

use Illuminate\Support\Collection;

class OrderSummary
{
    /**
     * Transform daftar order jadi ringkasan angka.
     *
     * @param  array<int, array{id: int, status: string, total: int, item_count: int}>  $orders
     * @return array{total_orders: int, total_revenue: int, by_status: array<string, int>, avg_items: float}
     */
    public function make(array $orders): array
    {
        // collect() = Collection: chain operasi data (count/where/sum/groupBy)
        // tanpa loop manual. Tiap method mengembalikan Collection baru,
        // input array asli tidak dimutasi.
        return collect($orders)
            ->pipe(fn (Collection $o) => [
                'total_orders' => $o->count(),
                'total_revenue' => $o->where('status', 'paid')->sum('total'),
                'by_status' => $o->groupBy('status')->map->count()->all(),
                'avg_items' => round($o->avg('item_count') ?? 0, 2),
            ]);
    }
}
