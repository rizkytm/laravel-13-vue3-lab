<?php

use App\Enums\MovementType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Payment\FakePaymentGateway;
use App\Services\Payment\MidtransPaymentGateway;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\RefundablePaymentGateway;
use App\Services\RefundService;
use Illuminate\Support\Facades\Http;

// Tahap 5 — Refund: paid → refunded, uang kembali via gateway, stok
// me-restock dengan jejak movement. Urutan: uang dulu, status sesudahnya.

/** Order paid dengan stok SUDAH berkurang (simulasi checkout via buy). */
function paidOrderWithStock(int $stock = 5, int $qty = 2): array
{
    $product = Product::factory()->create(['stock' => $stock]);
    $order = Order::factory()->create([
        'status' => OrderStatus::Paid,
        'total' => 100000,
        'transaction_id' => 'txn_ref_1',
    ]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => $qty]);

    // Simulasi checkout: stok berkurang $qty dengan jejak movement 'out'
    $product->buy($qty, $order);

    return [$order, $product];
}

it('refunds a paid order: gateway called, status refunded, stock restocked with trace', function () {
    [$order, $product] = paidOrderWithStock();

    $service = app(RefundService::class);
    expect($service->refund($order))->toBeTrue();

    // Gateway menerima uang kembali (singleton yang sama dipakai RefundService)
    expect(app(PaymentGateway::class)->refunds)->toBe(['txn_ref_1']);

    // Status + stok
    expect($order->fresh()->status)->toBe(OrderStatus::Refunded)
        ->and($product->fresh()->stock)->toBe(5);

    // Jejak movement 'in' terkait order
    $movement = $product->movements()->latest('id')->first();
    expect($movement->type)->toBe(MovementType::In)
        ->and($movement->quantity)->toBe(2)
        ->and($movement->order_id)->toBe($order->id)
        ->and($movement->stock_after)->toBe(5);
});

it('keeps SUM(movements) == stock through the refund', function () {
    [$order, $product] = paidOrderWithStock();

    app(RefundService::class)->refund($order);

    expect((int) $product->movements()->sum('quantity'))->toBe($product->fresh()->stock);
});

it('refuses refund for non-paid orders (status machine)', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending, 'total' => 10000]);

    expect(app(RefundService::class)->refund($order))->toBeFalse()
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and(app(FakePaymentGateway::class)->refunds)->toBeEmpty();
});

it('refuses double refund (refunded is terminal)', function () {
    [$order] = paidOrderWithStock();
    $service = app(RefundService::class);

    expect($service->refund($order))->toBeTrue()
        ->and($service->refund($order->fresh()))->toBeFalse();

    expect(app(PaymentGateway::class)->refunds)->toHaveCount(1); // uang hanya sekali
});

it('does not change status when gateway refuses the refund', function () {
    [$order, $product] = paidOrderWithStock();

    // Gateway tiruan yang menolak refund
    $gateway = new class extends FakePaymentGateway
    {
        public function refund(string $transactionId, int $amount): bool
        {
            return false;
        }
    };
    $this->app->instance(RefundablePaymentGateway::class, $gateway);

    expect(app(RefundService::class)->refund($order))->toBeFalse();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid) // uang belum kembali → tetap paid
        ->and($product->fresh()->stock)->toBe(3);             // stok tidak direstock
});

it('refuses refund for paid order without transaction_id (nothing to refund)', function () {
    $product = Product::factory()->create(['stock' => 5]);
    $order = Order::factory()->create(['status' => OrderStatus::Paid, 'transaction_id' => null, 'total' => 10000]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 1]);

    expect(fn () => app(RefundService::class)->refund($order))
        ->toThrow(RuntimeException::class);
});

it('calls the midtrans refund endpoint via Http::fake', function () {
    config(['payment.midtrans.server_key' => 'test-server-key']);

    Http::fake([
        'api.sandbox.midtrans.com/*' => Http::response(['status_code' => '200'], 200),
    ]);

    $result = app(MidtransPaymentGateway::class)->refund('mid-123', 40000);

    expect($result)->toBeTrue();
    Http::assertSent(fn ($request) => str_contains($request->url(), '/v2/mid-123/refund')
        && $request['refund_amount'] === 40000);
});

it('returns false when midtrans refund is rejected', function () {
    config(['payment.midtrans.server_key' => 'test-server-key']);

    Http::fake([
        'api.sandbox.midtrans.com/*' => Http::response(['status_code' => '400'], 200),
    ]);

    expect(app(MidtransPaymentGateway::class)->refund('mid-123', 40000))->toBeFalse();
});
