<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

// Structured logging: request_id context + payload event bisnis.

it('attaches request id context and logs structured checkout', function () {
    Log::spy();
    Queue::fake();

    $user = User::factory()->create();
    $product = Product::factory()->create(['stock' => 5]);
    Sanctum::actingAs($user);

    $this->withHeader('X-Request-Id', 'trace-abc-123')
        ->postJson('/api/v1/checkout', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
        ->assertCreated();

    // Middleware menanam correlation id ke log context request ini
    Log::shouldHaveReceived('withContext')
        ->withArgs(fn (array $context) => ($context['request_id'] ?? null) === 'trace-abc-123')
        ->once();

    // Event bisnis di-log dengan payload terstruktur (bukan kalimat bebas)
    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message, array $context) => $message === 'checkout.completed'
            && isset($context['order_id'], $context['user_id'], $context['total'], $context['items']))
        ->once();
});

it('generates request id when client does not send one', function () {
    Log::spy();

    $this->get('/products');

    Log::shouldHaveReceived('withContext')
        ->withArgs(fn (array $context) => isset($context['request_id']) && $context['request_id'] !== '')
        ->once();
});

it('returns request id in response header for tracing', function () {
    $this->get('/products')
        ->assertOk()
        ->assertHeader('X-Request-Id');
});
