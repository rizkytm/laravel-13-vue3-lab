<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    /**
     * GET /api/v1/orders — cursor pagination (token posisi, bukan nomor halaman).
     * Cocok untuk feed/infinite scroll: stabil saat data baru di-insert
     * di tengah pagination (offset bisa meleset/duplikat).
     * Scoping: hanya order milik user token.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);

        $orders = $request->user()->orders()
            ->with(['user', 'items.product'])
            ->withCount('items')
            ->cursorPaginate($perPage);

        return OrderResource::collection($orders);
    }

    /**
     * GET /api/v1/orders/{order} — ownership lewat policy yang sama
     * dengan endpoint web (satu aturan, dua permukaan).
     */
    public function show(Order $order): OrderResource
    {
        Gate::authorize('view', $order);

        $order->load(['user', 'items.product']);
        $order->loadCount('items'); // isi items_count di resource

        return new OrderResource($order);
    }

    /**
     * POST /api/v1/orders/{order}/cancel — customer batalkan order PENDING.
     * Order::cancel() = transisi status machine + restock berjejak (atomic).
     * Policy menolak non-pending/non-pemilik (403) sebelum sampai ke model.
     */
    public function cancel(Order $order): OrderResource
    {
        Gate::authorize('cancel', $order);

        $order->cancel();
        $order->load(['user', 'items.product']);
        $order->loadCount('items');

        return new OrderResource($order);
    }
}
