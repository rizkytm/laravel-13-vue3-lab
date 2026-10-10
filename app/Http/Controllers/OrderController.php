<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\View\View;

class OrderController extends Controller
{
    /**
     * Daftar order dengan eager loading bertingkat + withCount.
     *
     * Tanpa ini (N+1): 1 query orders + N query users + N query items
     * + N query products. Dengan ini: tetap 4 query berapa pun jumlah order.
     */
    public function index(): View
    {
        $orders = Order::query()
            ->with(['user', 'items.product'])
            ->withCount('items')
            ->latest()
            ->get();

        return view('orders.index', compact('orders'));
    }
}
