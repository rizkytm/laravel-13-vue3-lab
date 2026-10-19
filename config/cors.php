<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | ASVS 3.4.2 — jangan biarkan allowed_origins '*'. Alur SPA normal lewat
    | proxy Vite (same-origin), jadi CORS pada dasarnya TIDAK dipakai; daftar
    | di bawah hanya untuk konsumen API lintas-origin yang disengaja.
    |
    | Produksi: set CORS_ALLOWED_ORIGINS='https://app.example.com' (koma).
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://127.0.0.1:5173')),
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
