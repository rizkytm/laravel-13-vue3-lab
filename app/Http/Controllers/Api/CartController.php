<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CartItemRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Keranjang server-side (per user). Server otoritatif atas quantity
 * (clamp ke stok saat ini) — client tidak bisa menyisipkan quantity aneh.
 * Checkout membaca dari tabel ini, bukan dari payload request.
 */
class CartController extends Controller
{
    /** GET /api/v1/cart — isi keranjang + data produk terkini (harga/stok segar). */
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->payload($request)]);
    }

    /**
     * POST /api/v1/cart/items — tambah quantity (aditif) ke baris yang ada
     * atau buat baris baru. Transaksi + lock baris cart: klik ganda
     * bersamaan tidak kehilangan increment.
     */
    public function store(CartItemRequest $request): JsonResponse
    {
        $product = Product::query()->findOrFail($request->validated('product_id'));

        DB::transaction(function () use ($request, $product) {
            $existing = $request->user()->cartItems()
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            $this->persist(
                $request,
                $product,
                ($existing?->quantity ?? 0) + $request->validated('quantity'),
            );
        });

        return response()->json(['data' => $this->payload($request)], 201);
    }

    /** PATCH /api/v1/cart/items/{product} — set quantity absolut (0 = hapus). */
    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0'],
        ], [
            'quantity.min' => 'Quantity tidak boleh negatif.',
        ]);

        $this->persist($request, $product, $validated['quantity']);

        return response()->json(['data' => $this->payload($request)]);
    }

    /** DELETE /api/v1/cart/items/{product} */
    public function destroy(Request $request, Product $product): JsonResponse
    {
        $request->user()->cartItems()->where('product_id', $product->id)->delete();

        return response()->json(['data' => $this->payload($request)]);
    }

    /** DELETE /api/v1/cart — kosongkan. */
    public function clear(Request $request): JsonResponse
    {
        $request->user()->cartItems()->delete();

        return response()->json(['data' => []]);
    }

    /**
     * Tulis quantity final. Clamp server-side: stok BISA berkurang sejak
     * halaman dimuat — angka lama dari client tidak dipercaya.
     */
    private function persist(Request $request, Product $product, int $quantity): void
    {
        $cartItems = $request->user()->cartItems();

        if ($quantity === 0) {
            $cartItems->where('product_id', $product->id)->delete();

            return;
        }

        if ($product->stock === 0) {
            throw ValidationException::withMessages([
                'quantity' => "Stok {$product->name} habis.",
            ]);
        }

        // Clamp ke stok, bukan tolak — UX lebih baik
        $cartItems->updateOrCreate(
            ['product_id' => $product->id],
            ['quantity' => min($quantity, $product->stock)],
        );
    }

    /** Bentuk response seragam: item + snapshot produk terkini. */
    private function payload(Request $request): array
    {
        return $request->user()
            ->cartItems()
            ->with('product:id,name,price,stock')
            ->get()
            ->map(fn ($item) => [
                'product_id' => $item->product_id,
                'name' => $item->product->name,
                'price' => $item->product->price,
                'quantity' => $item->quantity,
                'stock' => $item->product->stock,
            ])
            ->all();
    }
}
