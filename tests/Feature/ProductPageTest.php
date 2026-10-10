<?php

use App\Models\Product;

it('lists products with accessor and count', function () {
    $product = Product::factory()->create(['name' => 'Sepatu Lari Pro']);

    $this->get('/products')
        ->assertOk()
        ->assertSee('Sepatu Lari Pro')
        ->assertSee('Rp '); // accessor ter-render
});

it('shows product detail by slug', function () {
    $product = Product::factory()->create();

    $this->get('/products/'.$product->slug)
        ->assertOk()
        ->assertSee($product->name);
});

it('returns 404 for unknown slug (implicit binding)', function () {
    $this->get('/products/tidak-ada')->assertNotFound();
});

it('reduces stock through buy endpoint', function () {
    $product = Product::factory()->create(['stock' => 10]);

    $this->post('/products/'.$product->slug.'/buy', ['quantity' => 4])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($product->fresh()->stock)->toBe(6);
});

it('rejects invalid quantity', function () {
    $product = Product::factory()->create(['stock' => 10]);

    $this->from('/products/'.$product->slug)
        ->post('/products/'.$product->slug.'/buy', ['quantity' => 0])
        ->assertSessionHasErrors('quantity');

    expect($product->fresh()->stock)->toBe(10);
});

it('rejects quantity above stock via StockAvailable rule', function () {
    $product = Product::factory()->create(['stock' => 2]);

    $this->from('/products/'.$product->slug)
        ->post('/products/'.$product->slug.'/buy', ['quantity' => 50])
        ->assertSessionHasErrors('quantity');

    expect($product->fresh()->stock)->toBe(2);
});
