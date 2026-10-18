<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Driver
    |--------------------------------------------------------------------------
    |
    | 'fake'     → FakePaymentGateway (tanpa HTTP, untuk dev/test)
    | 'http'     → HttpPaymentGateway (manggil payment provider asli)
    | 'midtrans' → MidtransPaymentGateway (Snap: buat sesi via HTTP,
    |               pembayaran dikonfirmasi lewat webhook diverifikasi
    |               signature — lihat PaymentWebhookController)
    |
    */

    'driver' => env('PAYMENT_DRIVER', 'fake'),

    'endpoint' => env('PAYMENT_ENDPOINT', 'https://sandbox.payment.example/charge'),

    // Midtrans Snap (sandbox). server_key dipakai untuk memverifikasi
    // signature webhook — JANGAN pernah dikirim ke frontend.
    'midtrans' => [
        'snap_url' => env('MIDTRANS_SNAP_URL', 'https://app.sandbox.midtrans.com/snap/v1/transactions'),
        'api_url' => env('MIDTRANS_API_URL', 'https://api.sandbox.midtrans.com'),
        'server_key' => env('MIDTRANS_SERVER_KEY', ''),
        'client_key' => env('MIDTRANS_CLIENT_KEY', ''),
    ],

];
