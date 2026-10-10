<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Services\Payment\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    /**
     * POST /api/v1/checkout — buat order dari items + charge gateway.
     *
     * Urutan: transaksi (order + items + kurangi stok) dulu, charge SETELAH commit.
     * Kalau charge gagal → order tetap Pending (idempotent retry di M5 via queue).
     */
    public function store(CheckoutRequest $request, PaymentGateway $gateway): JsonResponse
    {
        $items = $request->validated('items');

        $order = DB::transaction(function () use ($items, $request) {
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

                $total += $product->price * $item['quantity'];
            }

            $order->update(['total' => $total]);

            return $order;
        });

        $transactionId = $gateway->charge($order->total);

        $order->load('items.product');

        return response()->json([
            'data' => new OrderResource($order),
            'transaction_id' => $transactionId,
        ], 201);
    }
}
