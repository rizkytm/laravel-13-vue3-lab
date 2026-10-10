<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Driver
    |--------------------------------------------------------------------------
    |
    | 'fake' → FakePaymentGateway (tanpa HTTP, untuk dev/test)
    | 'http' → HttpPaymentGateway (manggil payment provider asli)
    |
    */

    'driver' => env('PAYMENT_DRIVER', 'fake'),

    'endpoint' => env('PAYMENT_ENDPOINT', 'https://sandbox.payment.example/charge'),

];
