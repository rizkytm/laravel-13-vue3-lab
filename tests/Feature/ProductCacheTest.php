<?php

use App\Models\Product;
use Illuminate\Support\Facades\DB;

// Cache::remember + invalidation via model event.

it('serves product list from cache', function () {
    Product::factory()->create(['name' => 'Kopi Lama']);

    $this->get('/products')->assertOk()->assertSee('Kopi Lama');

    // Ubah DB LANGSUNG (query builder, tanpa model event).
    // Halaman masih menampilkan 'Kopi Lama' → membuktikan SUDAH cache hit,
    // bukan query ke DB.
    $product = Product::first();
    DB::table('products')->where('id', $product->id)->update(['name' => 'Tanpa Event']);

    $this->get('/products')->assertSee('Kopi Lama');
});

it('invalidates cache when product saved through model', function () {
    Product::factory()->create(['name' => 'Kopi Lama']);
    $this->get('/products')->assertSee('Kopi Lama');

    // Via model event → Product::saved → Cache::forget('products.index')
    Product::first()->update(['name' => 'Kopi Baru']);

    $this->get('/products')->assertSee('Kopi Baru');
});

it('cache hit dengan driver database tidak merusak objek (serializable_classes)', function () {
    // Driver array TIDAK serialize — bug korupsi objek cuma muncul di store
    // yang serialize (database/file). Paksa database di test ini.
    config(['cache.default' => 'database']);

    Product::factory()->create(['name' => 'Kopi Uji']);

    $this->get('/products')->assertOk()->assertSee('Kopi Uji');

    // Kedua = cache HIT → unserialize dari cache table → harus tetap objek
    $this->get('/products')->assertOk()->assertSee('Kopi Uji');
});
