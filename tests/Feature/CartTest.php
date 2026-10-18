<?php

use App\Models\Product;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

// Keranjang server-side: per user, server otoritatif (clamp ke stok).

it('requires authentication for every cart endpoint', function () {
    $this->getJson('/api/v1/cart')->assertUnauthorized();
    $this->postJson('/api/v1/cart/items', ['product_id' => 1, 'quantity' => 1])->assertUnauthorized();
    $this->patchJson('/api/v1/cart/items/1', ['quantity' => 1])->assertUnauthorized();
    $this->deleteJson('/api/v1/cart/items/1')->assertUnauthorized();
    $this->deleteJson('/api/v1/cart')->assertUnauthorized();
});

it('adds an item and returns it with fresh product snapshot', function () {
    Sanctum::actingAs(User::factory()->create());
    $product = Product::factory()->create(['name' => 'Kopi', 'price' => 25000, 'stock' => 10]);

    $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 2])
        ->assertCreated()
        ->assertJsonPath('data.0.product_id', $product->id)
        ->assertJsonPath('data.0.name', 'Kopi')
        ->assertJsonPath('data.0.price', 25000)
        ->assertJsonPath('data.0.quantity', 2)
        ->assertJsonPath('data.0.stock', 10);
});

it('merges quantity when the same product is added twice (no duplicate rows)', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $product = Product::factory()->create(['stock' => 10]);

    $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 2]);
    $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 3]);

    expect($user->cartItems()->count())->toBe(1)
        ->and($user->cartItems()->sole()->quantity)->toBe(5);
});

it('clamps quantity to current stock instead of rejecting', function () {
    Sanctum::actingAs(User::factory()->create());
    $product = Product::factory()->create(['stock' => 4]);

    $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 99])
        ->assertCreated()
        ->assertJsonPath('data.0.quantity', 4); // clamp, bukan 422
});

it('rejects adding an out-of-stock product', function () {
    Sanctum::actingAs(User::factory()->create());
    $product = Product::factory()->create(['stock' => 0]);

    $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])
        ->assertStatus(422)
        ->assertJsonValidationErrors('quantity');
});

it('sets quantity absolutely via PATCH (0 removes the row)', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $product = Product::factory()->create(['stock' => 10]);
    $user->cartItems()->create(['product_id' => $product->id, 'quantity' => 2]);

    $this->patchJson("/api/v1/cart/items/{$product->id}", ['quantity' => 7])
        ->assertOk()
        ->assertJsonPath('data.0.quantity', 7);

    $this->patchJson("/api/v1/cart/items/{$product->id}", ['quantity' => 0])
        ->assertOk()
        ->assertJsonPath('data', []);

    expect($user->cartItems()->count())->toBe(0);
});

it('removes a single item and clears the whole cart', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $p1 = Product::factory()->create();
    $p2 = Product::factory()->create();
    $user->cartItems()->create(['product_id' => $p1->id, 'quantity' => 1]);
    $user->cartItems()->create(['product_id' => $p2->id, 'quantity' => 2]);

    $this->deleteJson("/api/v1/cart/items/{$p1->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->deleteJson('/api/v1/cart')->assertOk()->assertJsonPath('data', []);

    expect($user->cartItems()->count())->toBe(0);
});

it('validates cart item payload with Indonesian messages', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/cart/items', ['product_id' => 999999, 'quantity' => 0])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'product_id' => 'Produk tidak ditemukan.',
            'quantity' => 'Quantity minimal 1.',
        ]);
});

it('keeps carts isolated per user', function () {
    $owner = User::factory()->create();
    $product = Product::factory()->create(['stock' => 10]);
    $owner->cartItems()->create(['product_id' => $product->id, 'quantity' => 3]);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/cart')->assertOk()->assertJsonPath('data', []);
});
