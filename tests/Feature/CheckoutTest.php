<?php

use App\Services\Payment\PaymentGateway;

it('charges through the injected gateway and flashes transaction id', function () {
    $response = $this->post('/checkout', ['amount' => 150000]);

    $response->assertRedirect()
        ->assertSessionHas('transaction_id', 'txn_1');

    // Singleton yang sama dengan yang dipakai controller di request tadi
    expect(app(PaymentGateway::class)->charges)->toHaveCount(1)
        ->and(app(PaymentGateway::class)->charges[0])->toBe(150000);
});

it('rejects invalid amount', function () {
    $this->from('/')
        ->post('/checkout', ['amount' => 0])
        ->assertRedirect('/')
        ->assertSessionHasErrors('amount');
});

it('renders the checkout page with summary', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Ringkasan order contoh')
        ->assertSee('Container Binding');
});
