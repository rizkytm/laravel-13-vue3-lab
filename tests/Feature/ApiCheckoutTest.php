<?php

use App\Jobs\ProcessPayment;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

// Checkout membaca cart SERVER-SIDE (tabel cart_items) — payload tidak
// membawa items. Seed cart dulu, lalu POST /checkout tanpa body.

it('requires token (401 for guest)', function () {
    $this->postJson('/api/v1/checkout')->assertUnauthorized();
});

it('rejects checkout with an empty server-side cart', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/checkout')
        ->assertStatus(422)
        ->assertJsonStructure(['message', 'errors'])
        ->assertJsonPath('errors.items.0', 'Keranjang tidak boleh kosong.');

    expect(Order::count())->toBe(0);
});

it('ignores item payloads from the client (server cart wins)', function () {
    Queue::fake();

    $user = User::factory()->create();
    $inCart = Product::factory()->create(['price' => 10000, 'stock' => 5]);
    $notInCart = Product::factory()->create(['price' => 20000, 'stock' => 5]);
    $user->cartItems()->create(['product_id' => $inCart->id, 'quantity' => 2]);
    Sanctum::actingAs($user);

    // Client nakal menyisipkan produk yang tidak ada di keranjang
    $response = $this->postJson('/api/v1/checkout', [
        'items' => [
            ['product_id' => $inCart->id, 'quantity' => 2],
            ['product_id' => $notInCart->id, 'quantity' => 3],
        ],
    ]);

    $response->assertCreated()->assertJsonPath('data.total', 20000); // hanya item cart server

    $order = Order::findOrFail($response->json('data.id'));
    expect($order->items->pluck('product_id')->all())->toBe([$inCart->id])
        ->and($notInCart->fresh()->stock)->toBe(5); // tidak tersentuh
});

it('creates order from server cart, decrements stock, clears cart, queues payment', function () {
    Queue::fake();

    $user = User::factory()->create();
    $p1 = Product::factory()->create(['price' => 10000, 'stock' => 5]);
    $p2 = Product::factory()->create(['price' => 20000, 'stock' => 5]);
    $user->cartItems()->create(['product_id' => $p1->id, 'quantity' => 2]);
    $user->cartItems()->create(['product_id' => $p2->id, 'quantity' => 1]);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/checkout');

    // 201 TANPA transaction_id — pembayaran diproses di worker
    $response->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'status', 'total', 'items'], 'message'])
        ->assertJsonPath('data.total', 40000)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.transaction_id', null);

    expect($p1->fresh()->stock)->toBe(3)
        ->and($p2->fresh()->stock)->toBe(4)
        ->and($user->cartItems()->count())->toBe(0); // cart kosong setelah sukses

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
    $user->cartItems()->create(['product_id' => $product->id, 'quantity' => 1]);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/checkout')->assertCreated();

    // Listener sudah jalan (event discovery) → notification ikut ter-queue
    Queue::assertPushed(SendQueuedNotifications::class);
});

it('rolls back whole order — and keeps the cart — when stock is insufficient', function () {
    Queue::fake();

    $user = User::factory()->create();
    $ok = Product::factory()->create(['price' => 10000, 'stock' => 5]);
    $lack = Product::factory()->create(['price' => 20000, 'stock' => 1]);
    $user->cartItems()->create(['product_id' => $ok->id, 'quantity' => 2]);
    $user->cartItems()->create(['product_id' => $lack->id, 'quantity' => 5]);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/checkout')
        ->assertStatus(422)->assertJsonValidationErrors('items');

    expect(Order::count())->toBe(0)                    // order pertama ikut rollback
        ->and($ok->fresh()->stock)->toBe(5)            // pengurangan pertama dibatalkan
        ->and($user->cartItems()->count())->toBe(2);   // cart IKUT pulang (rollback)

    // Event/job tidak pernah sampai di-dispatch — gak ada charge tanpa order
    Queue::assertNothingPushed();
});

it('throttles checkout bursts with 429 (10/menit per user)', function () {
    Queue::fake();

    $user = User::factory()->create();
    $product = Product::factory()->create(['stock' => 1000]);
    Sanctum::actingAs($user);

    foreach (range(1, 10) as $attempt) {
        // Cart diisi ulang tiap iterasi — checkout mengosongkannya
        $user->cartItems()->create(['product_id' => $product->id, 'quantity' => 1]);
        $this->postJson('/api/v1/checkout')->assertCreated();
    }

    $user->cartItems()->create(['product_id' => $product->id, 'quantity' => 1]);
    $this->postJson('/api/v1/checkout')->assertStatus(429);
});
