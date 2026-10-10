<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * Daftar produk.
     * withCount('orderItems') → subquery COUNT, bukan hitung per baris di PHP.
     */
    public function index(): View
    {
        $products = Product::query()
            ->withCount('orderItems')
            ->latest()
            ->get();

        return view('products.index', compact('products'));
    }

    /**
     * Detail produk. Implicit binding {product:slug} → 404 otomatis kalau slug salah,
     * tanpa findOrFail manual.
     */
    public function show(Product $product): View
    {
        $product->loadCount('orderItems');

        return view('products.show', compact('product'));
    }

    /**
     * Beli (demo M2): kurangi stok dengan lockForUpdate.
     * Checkout penuh (order + items) dibangun di M4-M5.
     */
    public function buy(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $bought = $product->buy($validated['quantity']);

        return $bought
            ? redirect()->back()->with('success', "Berhasil membeli {$validated['quantity']} × {$product->name}. Sisa stok: {$product->stock}")
            : redirect()->back()->with('error', 'Stok tidak cukup');
    }
}
