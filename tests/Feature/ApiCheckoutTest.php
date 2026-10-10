<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Payment\PaymentGateway;
use Laravel\Sanctum\Sanctum;

it('requires token (401 for guest)', function () {
    $this->postJson('/api/v1/checkout', ['items' => []])->assertUnauthorized();
});

it('rejects empty cart at middleware gate', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/checkout', ['items' => []])
        ->assertStatus(422)
        ->assertJsonStructure(['message', 'errors'])
        ->assertJsonPath('errors.items.0', 'Keranjang tidak boleh kosong.');

    expect(Order::count())->toBe(0);
});

it('rejects invalid product id (exists rule)', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/checkout', [
        'items' => [['product_id' => 999999, 'quantity' => 1]],
    ])->assertStatus(422)->assertJsonValidationErrors('items.0.product_id');
});

it('creates order, decrements stock, charges gateway', function () {
    $user = User::factory()->create();
    $p1 = Product::factory()->create(['price' => 10000, 'stock' => 5]);
    $p2 = Product::factory()->create(['price' => 20000, 'stock' => 5]);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/checkout', [
        'items' => [
            ['product_id' => $p1->id, 'quantity' => 2],
            ['product_id' => $p2->id, 'quantity' => 1],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'status', 'total', 'items'], 'transaction_id'])
        ->assertJsonPath('data.total', 40000)
        ->assertJsonPath('data.status', 'pending');

    expect($p1->fresh()->stock)->toBe(3)
        ->and($p2->fresh()->stock)->toBe(4);

    $order = Order::findOrFail($response->json('data.id'));
    expect($order->items)->toHaveCount(2)
        ->and($order->user_id)->toBe($user->id);

    // Gateway singleton yang sama → charge tercatat (interface M1)
    expect(app(PaymentGateway::class)->charges)->toHaveCount(1)
        ->and(app(PaymentGateway::class)->charges[0])->toBe(40000);
});

it('rolls back whole order when any item has insufficient stock', function () {
    $user = User::factory()->create();
    $ok = Product::factory()->create(['price' => 10000, 'stock' => 5]);
    $lack = Product::factory()->create(['price' => 20000, 'stock' => 1]);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/checkout', [
        'items' => [
            ['product_id' => $ok->id, 'quantity' => 2], // lolos dulu, stok sempat -2
            ['product_id' => $lack->id, 'quantity' => 5], // gagal → semua batal
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('items');

    expect(Order::count())->toBe(0)                                    // order pertama ikut rollback
        ->and($ok->fresh()->stock)->toBe(5)                            // pengurangan pertama dibatalkan
        ->and(app(PaymentGateway::class)->charges)->toBeEmpty();       // gak ada charge tanpa order
});
