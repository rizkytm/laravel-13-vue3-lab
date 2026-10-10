<?php

use App\Models\Product;

// Fitur Eloquent di level model — butuh DB, jadi di Feature (RefreshDatabase).

it('generates slug automatically on creating (model event)', function () {
    $product = Product::create([
        'name' => 'Sepatu Boot Tahan Air',
        'price' => 250000,
        'stock' => 5,
    ]);

    expect($product->slug)->toStartWith('sepatu-boot-tahan-air');
});

it('keeps slug provided from factory (event does not overwrite)', function () {
    $product = Product::factory()->create(['slug' => 'kopi-aceh']);

    expect($product->slug)->toBe('kopi-aceh');
});

it('formats price via accessor without storing extra column', function () {
    $product = Product::factory()->create(['price' => 150000]);

    expect($product->formatted_price)->toBe('Rp 150.000')
        ->and($product->getAttributes())->not->toHaveKey('formatted_price');
});

it('casts json options to array automatically', function () {
    $product = Product::factory()->create(['options' => ['color' => 'red']]);

    expect($product->options)->toBeArray()
        ->and($product->options['color'])->toBe('red');
});

it('filters products by json option (scope)', function () {
    Product::factory()->create(['options' => ['color' => 'red']]);
    Product::factory()->create(['options' => ['color' => 'blue']]);

    expect(Product::query()->withOption('color', 'red')->count())->toBe(1);
});

it('filters in-stock products (scope)', function () {
    Product::factory()->create(['stock' => 0, 'is_active' => true]);
    Product::factory()->create(['stock' => 3, 'is_active' => false]);
    Product::factory()->create(['stock' => 3, 'is_active' => true]);

    expect(Product::query()->inStock()->count())->toBe(1);
});

it('upserts products by slug without touching other columns', function () {
    $product = Product::factory()->create(['slug' => 'kopi-hitam', 'price' => 10000]);

    Product::upsert(
        [['slug' => 'kopi-hitam', 'name' => 'Kopi Hitam Premium', 'price' => 12000]],
        ['slug'],
        ['name', 'price'],
    );

    expect($product->refresh()->price)->toBe(12000)
        ->and($product->stock)->toBeGreaterThan(0); // kolom yang tidak disebut tetap
});
