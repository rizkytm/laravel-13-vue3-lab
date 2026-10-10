<?php

use App\Jobs\ProcessPayment;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;
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

it('creates order, decrements stock, queues payment (M5 async)', function () {
    Queue::fake();

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

    // 201 TANPA transaction_id — pembayaran diproses di worker
    $response->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'status', 'total', 'items'], 'message'])
        ->assertJsonPath('data.total', 40000)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.transaction_id', null);

    expect($p1->fresh()->stock)->toBe(3)
        ->and($p2->fresh()->stock)->toBe(4);

    $order = Order::findOrFail($response->json('data.id'));
    expect($order->items)->toHaveCount(2)
        ->and($order->user_id)->toBe($user->id);

    // Payment masuk queue, bukan jalan di request
    Queue::assertPushed(
        ProcessPayment::class,
        fn (ProcessPayment $job) => $job->order->is($order)
    );
});

it('fires OrderPlaced event on successful checkout', function () {
    Queue::fake();
    $user = User::factory()->create();
    $product = Product::factory()->create(['stock' => 5]);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/checkout', [
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ])->assertCreated();

    // Listener sudah jalan (event discovery) → notification ikut ter-queue
    Queue::assertPushed(SendQueuedNotifications::class);
});

it('rolls back whole order when any item has insufficient stock', function () {
    Queue::fake();

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

    expect(Order::count())->toBe(0)                    // order pertama ikut rollback
        ->and($ok->fresh()->stock)->toBe(5);           // pengurangan pertama dibatalkan

    // Event/job tidak pernah sampai di-dispatch — gak ada charge tanpa order
    Queue::assertNothingPushed();
});
