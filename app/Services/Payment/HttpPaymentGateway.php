<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Http;

/**
 * Implementasi nyata: charge via HTTP ke payment provider.
 * Interface yang sama dengan FakePaymentGateway — controller tidak
 * tahu dan tidak perlu tahu driver mana yang aktif.
 */
class HttpPaymentGateway implements PaymentGateway
{
    public function charge(int $amount): string
    {
        $response = Http::post(config('payment.endpoint'), [
            'amount' => $amount,
            'currency' => 'IDR',
        ]);

        $response->throw(); // 4xx/5xx → RequestException, jangan telan error diam-diam

        return $response->json('transaction_id');
    }
}
