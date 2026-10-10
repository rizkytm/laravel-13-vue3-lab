<?php

use App\Services\Payment\FakePaymentGateway;
use App\Services\Payment\PaymentGateway;

// Feature test tapi untuk perilaku container — butuh Application,
// jadi pakai Tests\TestCase (yang bootstraps Laravel), bukan test unit murni.

it('resolves PaymentGateway interface to FakePaymentGateway', function () {
    expect(app(PaymentGateway::class))->toBeInstanceOf(FakePaymentGateway::class);
});

it('resolves PaymentGateway as a singleton (1 instance)', function () {
    expect(app(PaymentGateway::class))->toBe(app(PaymentGateway::class));
});
