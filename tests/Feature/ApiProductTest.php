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
