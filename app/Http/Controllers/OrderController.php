<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
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
        $order->loadCount('items'); // isi items_count di resource

        return view('orders.show', compact('order'));
    }

    /**
     * Invoice via SIGNED URL — endpoint tanpa auth biasa, otorisasi =
     * signature valid + belum expired (middleware 'signed').
     * Kasus nyata: kirim link invoice ke guest/customer via email.
     * Link-nya digenerate temporarySignedRoute (30 menit) di halaman show.
     */
    public function invoice(Order $order): View
    {
        $order->load(['user', 'items.product']);

        return view('orders.invoice', compact('order'));
    }
}
