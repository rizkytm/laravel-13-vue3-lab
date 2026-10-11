<?php

use App\Models\Product;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('requires token (401 for guest)', function () {
    $this->getJson('/api/v1/products')->assertUnauthorized();
});

it('lists products with offset pagination', function () {
    Product::factory()->count(12)->create();
    Sanctum::actingAs(User::factory()->create());

    $page1 = $this->getJson('/api/v1/products?per_page=5')
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('meta.total', 12)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonStructure(['data' => ['*' => ['id', 'name', 'slug', 'price', 'stock', 'options']]]);

    $page2 = $this->getJson('/api/v1/products?page=2')
        ->assertOk()
        ->assertJsonPath('meta.current_page', 2);

    // Halaman berbeda = item berbeda (offset bekerja)
    $ids1 = array_column($page1->json('data'), 'id');
    $ids2 = array_column($page2->json('data'), 'id');
    expect(array_intersect($ids1, $ids2))->toBeEmpty();
});

it('caps per_page to a sane maximum', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/products?per_page=9999')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 50);
});

it('sorts by whitelisted column and direction', function () {
    Product::factory()->create(['name' => 'B', 'price' => 200]);
    Product::factory()->create(['name' => 'A', 'price' => 100]);
    Product::factory()->create(['name' => 'C', 'price' => 300]);
    Sanctum::actingAs(User::factory()->create());

    $prices = $this->getJson('/api/v1/products?sort=price&dir=asc')
        ->assertOk()
        ->json('data.*.price');
    expect($prices)->toBe([100, 200, 300]);

    $names = $this->getJson('/api/v1/products?sort=name&dir=asc')
        ->assertOk()
        ->json('data.*.name');
    expect($names)->toBe(['A', 'B', 'C']);
});

it('ignores sort param outside whitelist (fallback, bukan 500)', function () {
    Product::factory()->count(2)->create();
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/products?sort=DROP%20TABLE&dir=asc')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('shows single product with count', function () {
    $product = Product::factory()->create();
    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/v1/products/{$product->slug}")
        ->assertOk()
        ->assertJsonPath('data.id', $product->id)
        ->assertJsonPath('data.name', $product->name);
});

it('returns 404 JSON for unknown product', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/products/tidak-ada')->assertNotFound();
});
