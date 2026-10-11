<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * GET /api/v1/products — offset pagination (page number).
     * Cocok untuk list statis dengan nomor halaman yang bisa di-share.
     * ?sort=name|price|created_at & ?dir=asc|desc — whitelist, bukan input mentah
     * (kolom di-queryBuilder HARUS dari daftar izin → cegah SQL injection).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);

        $sort = in_array($request->query('sort'), ['name', 'price', 'created_at'], true)
            ? $request->query('sort')
            : 'created_at';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $products = Product::query()
            ->withCount('orderItems')
            ->orderBy($sort, $dir)
            ->orderBy('id') // tie-breaker → urutan stabil antar halaman
            ->paginate($perPage);

        return ProductResource::collection($products);
    }

    /**
     * GET /api/v1/products/{product:slug} — binding by slug (route parameter).
     */
    public function show(Product $product): ProductResource
    {
        $product->loadCount('orderItems');

        return new ProductResource($product);
    }
}
