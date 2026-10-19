<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

// Cache::remember + invalidation via version stamp di model event.

it('serves product list from cache', function () {
    Product::factory()->create(['name' => 'Kopi Lama']);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonFragment(['name' => 'Kopi Lama']);

    // Ubah DB LANGSUNG (query builder, tanpa model event).
    // Response masih 'Kopi Lama' → membuktikan SUDAH cache hit,
    // bukan query ke DB.
    $product = Product::first();
    DB::table('products')->where('id', $product->id)->update(['name' => 'Tanpa Event']);

    $this->getJson('/api/v1/products')->assertJsonFragment(['name' => 'Kopi Lama']);
});

it('invalidates cache when product saved through model', function () {
    Product::factory()->create(['name' => 'Kopi Lama']);
    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/products')->assertJsonFragment(['name' => 'Kopi Lama']);

    // Via model event → Product::saved → version stamp naik → key lama kelewat
    Product::first()->update(['name' => 'Kopi Baru']);

    $this->getJson('/api/v1/products')->assertJsonFragment(['name' => 'Kopi Baru']);
});

// Regresi: InventoryService update stok via QUERY BUILDER (melewati event
// Eloquent). Tanpa bump versi eksplisit, list menyajikan stok lama sampai
// TTL 5 menit habis — padahal detail (tanpa cache) sudah benar.
it('refreshes cached stock after InventoryService changes it', function () {
    $product = Product::factory()->create(['stock' => 5]);
    Sanctum::actingAs(User::factory()->create());

    // Prime cache list — nilai 5 ter-cache
    $this->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonFragment(['stock' => 5]);

    // Beli 2 lewat jalur resmi (query builder + movement)
    expect($product->buy(2))->toBeTrue();

    // Cache MISS → list wajib menampilkan stok terbaru (3), bukan 5
    $this->getJson('/api/v1/products')
        ->assertJsonFragment(['stock' => 3]);

    // Jejak movement tetap sinkron dengan stok (invariant)
    expect((int) $product->movements()->sum('quantity'))->toBe(3);
});

it('cache hit dengan driver database tidak merusak objek (serializable_classes)', function () {
    // Driver array TIDAK serialize — bug korupsi objek cuma muncul di store
    // yang serialize (database/file). Paksa database di test ini.
    config(['cache.default' => 'database']);
    Product::factory()->create(['name' => 'Kopi Uji']);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/products')->assertOk()->assertJsonFragment(['name' => 'Kopi Uji']);

    // Kedua = cache HIT → unserialize dari cache table → harus tetap objek
    $this->getJson('/api/v1/products')->assertOk()->assertJsonFragment(['name' => 'Kopi Uji']);
});
