<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Payment\MidtransPaymentGateway;
use Illuminate\Support\Facades\Http;

// Tahap 4 — driver Midtrans: kontrak Snap (Http::fake) + webhook signature.

/** Bangun payload webhook + signature yang valid untuk order. */
function midtransWebhook(Order $order, string $status = 'settlement', array $overrides = []): array
{
    $payload = array_merge([
        'order_id' => (string) $order->id,
        'status_code' => '200',
        'gross_amount' => (string) $order->total,
        'transaction_status' => $status,
        'transaction_id' => 'mid-123',
        'signature_key' => '',
    ], $overrides);

    $payload['signature_key'] = hash('sha512',
        $payload['order_id'].$payload['status_code'].$payload['gross_amount']
            .config('payment.midtrans.server_key')
    );

    return $payload;
}

beforeEach(function () {
    config(['payment.midtrans.server_key' => 'test-server-key']);
});

it('creates a snap transaction with basic auth and returns token + redirect', function () {
    Http::fake([
        'app.sandbox.midtrans.com/*' => Http::response([
            'token' => 'snap-token-abc',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v1/abc',
        ], 200),
    ]);

    config(['payment.driver' => 'midtrans']);

    $order = Order::factory()->create(['total' => 40000]);
    $result = app(MidtransPaymentGateway::class)->createTransaction($order);

    expect($result['token'])->toBe('snap-token-abc')
        ->and($result['redirect_url'])->toContain('snap/v1/abc');

    Http::assertSent(function ($request) {
        return $request->url() === config('payment.midtrans.snap_url')
            && $request['transaction_details']['order_id'] === (string) order_id_of($request)
            && $request['transaction_details']['gross_amount'] === 40000
            && str_starts_with($request->header('Authorization')[0], 'Basic ');
    });
});

it('satisfies the PaymentGateway contract via charge() (returns snap token)', function () {
    Http::fake([
        'app.sandbox.midtrans.com/*' => Http::response(['token' => 'snap-xyz', 'redirect_url' => 'x'], 200),
    ]);

    $token = app(MidtransPaymentGateway::class)->charge(50000);

    expect($token)->toBe('snap-xyz');
    Http::assertSentCount(1);
});

it('throws on snap API error (jangan telan error diam-diam)', function () {
    Http::fake([
        'app.sandbox.midtrans.com/*' => Http::response(['error_messages' => ['gross_amount must be positive']], 400),
    ]);

    expect(fn () => app(MidtransPaymentGateway::class)->charge(50000))
        ->toThrow(Illuminate\Http\Client\RequestException::class);
});

it('marks order paid on valid settlement webhook', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending, 'total' => 40000]);

    $this->postJson('/api/payments/midtrans/webhook', midtransWebhook($order))
        ->assertOk();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->transaction_id)->toBe('mid-123')
        ->and($order->paid_at)->not->toBeNull();
});

// Regresi: ProcessPayment lama menandai paid SETELAH charge(). Untuk gateway
// async (Midtrans), charge() hanya membuat SESI — paid prematur = order
// terlihat lunas sebelum user benar-benar bayar. Job harus diam, webhook
// yang mengesahkan.
it('processpayment leaves async-gateway order pending — webhook is the authority', function () {
    config(['payment.driver' => 'midtrans']);

    Http::fake(['app.sandbox.midtrans.com/*' => Http::response(['token' => 't', 'redirect_url' => 'r'], 200)]);

    $order = Order::factory()->create(['status' => OrderStatus::Pending, 'total' => 40000]);

    (new \App\Jobs\ProcessPayment($order))->handle(app(\App\Services\Payment\PaymentGateway::class));

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Pending)      // JANGAN paid
        ->and($order->paid_at)->toBeNull()
        ->and($order->transaction_id)->toBeNull();

    // Webhook signature valid → baru paid
    $this->postJson('/api/payments/midtrans/webhook', midtransWebhook($order))->assertOk();
    expect($order->refresh()->status)->toBe(OrderStatus::Paid);
});

it('still marks paid synchronously for the fake (sync) gateway', function () {
    // Regresi arah sebaliknya: jalur lama (fake driver) tidak boleh rusak
    $order = Order::factory()->create(['status' => OrderStatus::Pending, 'total' => 40000]);

    (new \App\Jobs\ProcessPayment($order))->handle(app(\App\Services\Payment\PaymentGateway::class));

    expect($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->paid_at)->not->toBeNull();
});

it('rejects webhook with tampered signature (403)', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending, 'total' => 40000]);

    $payload = midtransWebhook($order);
    $payload['gross_amount'] = '1'; // ubah amount tanpa perbarui signature
    $payload['signature_key'] = hash('sha512', $payload['order_id'].$payload['status_code']
        .config('payment.midtrans.server_key').'1'); // signature salah

    $this->postJson('/api/payments/midtrans/webhook', $payload)->assertForbidden();

    expect($order->refresh()->status)->toBe(OrderStatus::Pending);
});

it('rejects webhook with empty signature (403)', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $payload = midtransWebhook($order);
    $payload['signature_key'] = '';

    $this->postJson('/api/payments/midtrans/webhook', $payload)->assertForbidden();
});

it('is idempotent — repeated settlement webhook does not double-apply', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending, 'total' => 40000]);
    $payload = midtransWebhook($order);

    $this->postJson('/api/payments/midtrans/webhook', $payload)->assertOk();
    $this->postJson('/api/payments/midtrans/webhook', $payload)->assertOk(); // retry Midtrans

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->paid_at)->not->toBeNull();
});

it('maps cancel and failure statuses via the status machine', function () {
    $cancelled = Order::factory()->create(['status' => OrderStatus::Pending, 'total' => 10000]);
    $this->postJson('/api/payments/midtrans/webhook', midtransWebhook($cancelled, 'cancel'))->assertOk();
    expect($cancelled->refresh()->status)->toBe(OrderStatus::Cancelled);

    $failed = Order::factory()->create(['status' => OrderStatus::Pending, 'total' => 10000]);
    $this->postJson('/api/payments/midtrans/webhook', midtransWebhook($failed, 'deny'))->assertOk();
    expect($failed->refresh()->status)->toBe(OrderStatus::Failed);
});

it('returns 404 for unknown order even with valid signature shape', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending, 'total' => 40000]);
    $payload = midtransWebhook($order);
    $payload['order_id'] = '999999';
    // signature dihitung ulang untuk order_id palsu → valid secara kriptografi
    $payload['signature_key'] = hash('sha512', $payload['order_id'].$payload['status_code']
        .$payload['gross_amount'].config('payment.midtrans.server_key'));

    $this->postJson('/api/payments/midtrans/webhook', $payload)->assertNotFound();
});

/** Helper: ambil order_id dari request snapshot Http::fake. */
function order_id_of($request): int
{
    return (int) $request['transaction_details']['order_id'];
}
