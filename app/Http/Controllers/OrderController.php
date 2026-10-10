<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OrderController extends Controller
{
    /**
     * Order milik user login SAJA (scoping).
     * Query dari relasi → user lain gak muncul, gak perlu filter manual.
     */
    public function index(Request $request): View
    {
        // Eager loading bertingkat + withCount — tetap 4 query
        // berapa pun jumlah order (lihat test anti N+1 di OrdersPageTest).
        $orders = $request->user()->orders()
            ->with(['user', 'items.product'])
            ->withCount('items')
            ->latest()
            ->get();

        return view('orders.index', compact('orders'));
    }

    /**
     * Detail satu order — wajib pemilik (OrderPolicy::view).
     * Gate::authorize → AuthorizationException → 403 kalau bukan owner.
     */
    public function show(Request $request, Order $order): View
    {
        Gate::authorize('view', $order);

        $order->load(['user', 'items.product']);

        return view('orders.show', compact('order'));
    }
}
