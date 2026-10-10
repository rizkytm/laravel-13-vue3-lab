<?php

use App\Events\StockUpdated;
use App\Models\Product;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Support\Facades\Queue;

// Broadcasting: stok berubah → event ke channel 'products' (queued).

it('broadcasts stock update with explicit allow-list payload', function () {
    Queue::fake();

    $product = Product::factory()->create(['stock' => 5]);

    // Lewat endpoint web — event di-fire controller SETELAH buy() commit,
    // bukan dari dalam transaction (lihat rollback test di ApiCheckoutTest)
    $this->post("/products/{$product->slug}/buy", ['quantity' => 2]);

    Queue::assertPushed(BroadcastEvent::class, function (BroadcastEvent $job) use ($product) {
        return $job->event instanceof StockUpdated
            && $job->event->broadcastOn()[0]->name === 'products'
            // Hanya field di allow-list yang keluar — bukan seluruh model
            && $job->event->broadcastWith() === ['product_id' => $product->id, 'stock' => 3];
    });
});

it('does not broadcast when buy fails (stock insufficient)', function () {
    Queue::fake();

    $product = Product::factory()->create(['stock' => 1]);
    $this->post("/products/{$product->slug}/buy", ['quantity' => 5]);

    Queue::assertNotPushed(BroadcastEvent::class);
});
