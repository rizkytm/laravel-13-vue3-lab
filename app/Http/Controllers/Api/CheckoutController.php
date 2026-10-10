<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Events\OrderPlaced;
use App\Events\StockUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Jobs\ProcessPayment;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    /**
     * POST /api/v1/checkout — buat order dari items, queue payment.
     *
     * Urutan:
     * 1. Transaksi DB (order + items + kurangi stok) — atomic, bisa rollback
     * 2. Setelah commit: fire event OrderPlaced (→ notifikasi via listener)
     * 3. Queue ProcessPayment (→ charge gateway di worker, bukan di request)
     *
     * Response 201 TANPA transaction_id — pembayaran diproses async.
     * Id selesai (paid/failed) terlihat lewat GET /api/v1/orders/{id}.
     */
    public function store(CheckoutRequest $request): JsonResponse
    {
        $items = $request->validated('items');

        $touchedProducts = [];

        $order = DB::transaction(function () use ($items, $request, &$touchedProducts) {
            $order = Order::create([
                'user_id' => $request->user()->id,
                'status' => OrderStatus::Pending,
                'total' => 0,
            ]);

            $total = 0;

            foreach ($items as $item) {
                // lockForUpdate di Product::buy — dua checkout paralel gak oversell.
                // ValidationException di sini melempar keluar transaction → rollback
                // SELURUH order (item pertama yang sudah dibuat ikut dibatalkan).
                $product = Product::query()->findOrFail($item['product_id']);

                if (! $product->buy($item['quantity'])) {
                    throw ValidationException::withMessages([
                        'items' => "Stok {$product->name} tidak mencukupi.",
                    ]);
                }

                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $product->price, // snapshot harga saat checkout
                ]);

                $touchedProducts[] = $product;

                $total += $product->price * $item['quantity'];
            }

            $order->update(['total' => $total]);

            return $order;
        });

        // Event fire SETELAH commit — listener tidak boleh melihat data
        // yang belum konsisten (atau ikut ter-rollback).
        // Broadcast stok juga di sini: kalau transaksi rollback, product
        // di $touchedProducts tidak pernah di-broadcast (stok aslinya juga gak berubah).
        foreach ($touchedProducts as $product) {
            event(new StockUpdated($product));
        }
        event(new OrderPlaced($order));
        ProcessPayment::dispatch($order);

        $order->load('items.product');

        // Structured log (JSON channel): event bisnis + context —
        // request_id dari AssignRequestId ikut otomatis di baris ini.
        Log::info('checkout.completed', [
            'order_id' => $order->id,
            'user_id' => $request->user()->id,
            'total' => $order->total,
            'items' => count($items),
        ]);

        return response()->json([
            'data' => new OrderResource($order),
            'message' => 'Pembayaran sedang diproses.',
        ], 201);
    }
}
