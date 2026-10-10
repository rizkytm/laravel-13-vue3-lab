<?php

use App\Models\Product;

// Race-safety stok: baca-lock-tulis dalam satu transaction.

it('decrements stock after successful buy', function () {
    $product = Product::factory()->create(['stock' => 5]);

    expect($product->buy(3))->toBeTrue()
        ->and($product->fresh()->stock)->toBe(2);
});

it('refuses to sell more than available stock', function () {
    $product = Product::factory()->create(['stock' => 5]);

    expect($product->buy(10))->toBeFalse()
        ->and($product->fresh()->stock)->toBe(5);
});

it('never lets stock go negative on repeated buys', function () {
    $product = Product::factory()->create(['stock' => 2]);

    $product->buy(2);
    $product->buy(1); // stok habis — harus gagal, bukan -1

    expect($product->fresh()->stock)->toBe(0);
});
