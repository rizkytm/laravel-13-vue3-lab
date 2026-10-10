<?php

namespace App\Http\Controllers;

use App\Events\StockUpdated;
use App\Http\Requests\BuyProductRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * Daftar produk.
     * withCount('orderItems') → subquery COUNT, bukan hitung per baris di PHP.
     *
     * Cache::remember: hasil query di-cache 5 menit. Invalidation ada di
     * model event Product::saved/deleted — kalau lupa, halaman basi.
     */
    public function index(): View
    {
        $products = Cache::remember('products.index', 300, function () {
            return Product::query()
                ->withCount('orderItems')
                ->latest()
                ->get();
        });

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
     * Validasi pindah ke BuyProductRequest — controller menerima
     * data yang sudah valid. Checkout penuh dibangun di M4-M5.
     */
    public function buy(BuyProductRequest $request, Product $product): RedirectResponse
    {
        $quantity = $request->validated('quantity');

        $bought = $product->buy($quantity);

        if ($bought) {
            // buy() sudah commit (transaction-nya berdiri sendiri di sini) →
            // baru sekarang aman untuk broadcast stok berubah.
            event(new StockUpdated($product));
        }

        return $bought
            ? redirect()->back()->with('success', "Berhasil membeli {$quantity} × {$product->name}. Sisa stok: {$product->stock}")
            : redirect()->back()->with('error', 'Stok tidak cukup'); // fallback race — lapisan utama di rule + lock
    }

    /**
     * Upload gambar produk ke disk privat.
     * store() → path random (hash nama), NAMA FILE USER TIDAK PERNAH
     * dipakai langsung — cegah path traversal / overwrite file sembarangan.
     */
    public function uploadImage(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'max:2048'], // tipe + batas 2MB
        ]);

        $path = $request->file('image')->store('products', 'local');
        $product->update(['image_path' => $path]);

        return redirect()->back()->with('success', 'Gambar produk diunggah.');
    }

    /**
     * Sajikan gambar dari disk privat — file TIDAK publicly accessible
     * di filesystem, selalu lewat controller (bisa ditambah otorisasi nanti).
     */
    public function image(Product $product)
    {
        abort_unless($product->image_path, 404);

        return Storage::disk('local')->response($product->image_path);
    }
}
