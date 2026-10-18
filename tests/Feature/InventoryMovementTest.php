<?php

use App\Enums\MovementType;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

// Inventory movement: stok TIDAK PERNAH berubah tanpa jejak.
// products.stock = kolom turunan; inventory_movements = sumber kebenaran audit.

it('records an initial in-movement when a product is created', function () {
    $product = Product::factory()->create(['stock' => 10]);

    $movement = $product->movements()->sole();

    expect($movement->type)->toBe(MovementType::In)
        ->and($movement->quantity)->toBe(10)
        ->and($movement->stock_after)->toBe(10)
        ->and($movement->reason)->toBe('Stok awal')
        ->and($movement->order_id)->toBeNull();
});

it('creates no initial movement for a zero-stock product', function () {
    $product = Product::factory()->create(['stock' => 0]);

    expect($product->movements()->count())->toBe(0);
});

it('records an out movement on every buy with signed quantity', function () {
    $product = Product::factory()->create(['stock' => 5]);

    $product->buy(3);

    $movement = $product->movements()->latest('id')->first();
    expect($movement->type)->toBe(MovementType::Out)
        ->and($movement->quantity)->toBe(-3) // signed: keluar = negatif
        ->and($movement->stock_after)->toBe(2)
        ->and($product->fresh()->stock)->toBe(2);
});

it('records nothing when buy fails for insufficient stock', function () {
    $product = Product::factory()->create(['stock' => 5]);

    expect($product->buy(10))->toBeFalse()
        ->and($product->fresh()->stock)->toBe(5)
        ->and($product->movements()->count())->toBe(1); // hanya stok awal
});

it('links checkout movements to the order', function () {
    $product = Product::factory()->create(['stock' => 5]);
    $order = Order::factory()->create();

    $product->buy(2, $order);

    $movement = $product->movements()->latest('id')->first();
    expect($movement->order_id)->toBe($order->id)
        ->and($movement->reason)->toBe("Checkout order #{$order->id}");
});

it('restocks with an in movement via the service', function () {
    $product = Product::factory()->create(['stock' => 2]);

    app(InventoryService::class)->increment($product, 8, 'Restock supplier');

    $movement = $product->movements()->latest('id')->first();
    expect($product->fresh()->stock)->toBe(10)
        ->and($movement->type)->toBe(MovementType::In)
        ->and($movement->quantity)->toBe(8)
        ->and($movement->stock_after)->toBe(10);
});

it('adjusts to an absolute value recording the delta', function () {
    $product = Product::factory()->create(['stock' => 7]);

    app(InventoryService::class)->adjust($product, 5, 'Stock opname');

    $movement = $product->movements()->latest('id')->first();
    expect($product->fresh()->stock)->toBe(5)
        ->and($movement->type)->toBe(MovementType::Adjust)
        ->and($movement->quantity)->toBe(-2) // delta: 5 - 7
        ->and($movement->stock_after)->toBe(5)
        ->and($movement->reason)->toBe('Stock opname');
});

it('keeps SUM(quantity) == stock after a mixed sequence of operations', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $service = app(InventoryService::class);

    $product->buy(3);              // 10 → 7
    $service->increment($product, 5, 'Restock');  // 7 → 12
    $product->buy(2);              // 12 → 10
    $service->adjust($product, 4, 'Opname');      // 10 → 4

    expect($product->fresh()->stock)->toBe(4)
        ->and((int) $product->movements()->sum('quantity'))->toBe(4);
});

it('rolls back both stock and movement when checkout fails mid-transaction', function () {
    Queue::fake();
    $user = User::factory()->create();
    $ok = Product::factory()->create(['stock' => 5]);
    $lack = Product::factory()->create(['stock' => 1]);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/checkout', [
        'items' => [
            ['product_id' => $ok->id, 'quantity' => 2],
            ['product_id' => $lack->id, 'quantity' => 5],
        ],
    ])->assertStatus(422);

    // Gerakan 'out' untuk $ok ikut ter-rollback — jejak tidak boleh
    // meninggalkan stok yang tidak ada
    expect($ok->fresh()->stock)->toBe(5)
        ->and($ok->movements()->count())->toBe(1) // hanya stok awal
        ->and($lack->fresh()->stock)->toBe(1)
        ->and($lack->movements()->count())->toBe(1);
});

it('restores seeded stock through movements too (seeder tanpa WithoutModelEvents)', function () {
    $this->seed();

    $product = Product::first();

    expect($product->movements()->count())->toBe(1)
        ->and((int) $product->movements()->sum('quantity'))->toBe($product->stock);
});
