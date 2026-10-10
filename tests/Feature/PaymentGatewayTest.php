<?php

use App\Services\Payment\FakePaymentGateway;
use App\Services\Payment\HttpPaymentGateway;
use App\Services\Payment\PaymentGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

// Driver gateway: fake (default) vs http — dipilih lewat config, bukan kode.

it('charges via HTTP client and returns provider transaction id', function () {
    Http::fake(['*' => Http::response(['transaction_id' => 'ext_777'])]);

    $gateway = new HttpPaymentGateway;

    expect($gateway->charge(50_000))->toBe('ext_777');

    Http::assertSent(function (Request $request) {
        return $request->url() === config('payment.endpoint')
            && $request['amount'] === 50_000
            && $request['currency'] === 'IDR';
    });
});

it('throws when gateway responds with error', function () {
    Http::fake(['*' => Http::response('server error', 500)]);

    // throw() → jangan kembalikan "sukses" palsu saat provider gagal
    expect(fn () => (new HttpPaymentGateway)->charge(1000))
        ->toThrow(\Illuminate\Http\Client\RequestException::class);
});

it('binds PaymentGateway to HttpPaymentGateway when driver=http', function () {
    config(['payment.driver' => 'http']);

    expect(app(PaymentGateway::class))->toBeInstanceOf(HttpPaymentGateway::class);
});

it('binds PaymentGateway to FakePaymentGateway by default', function () {
    expect(app(PaymentGateway::class))->toBeInstanceOf(FakePaymentGateway::class);
});
