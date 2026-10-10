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
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);

        $products = Product::query()
            ->withCount('orderItems')
            ->paginate($perPage);

        return ProductResource::collection($products);
    }

    /**
     * GET /api/v1/products/{product} — binding by id (bawaan apiResource).
     */
    public function show(Product $product): ProductResource
    {
        $product->loadCount('orderItems');

        return new ProductResource($product);
    }
}
