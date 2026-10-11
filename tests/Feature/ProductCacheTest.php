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
