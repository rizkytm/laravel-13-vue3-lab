<?php

namespace App\Services\Payment;

use App\Models\Order;
use Illuminate\Support\Facades\Http;

/**
 * Driver Midtrans (Snap) — CONTOH NYATA provider redirect.
 *
 * Alur Snap:
 * 1. createTransaction(order) → POST /snap/v1/transactions → {token, redirect_url}
 *    (user diarahkan ke halaman bayar Midtrans)
 * 2. Midtrans memanggil WEBHOOK kami (payment.webhook) dengan payload
 *    berisi signature_key — diverifikasi di sini, lalu order → paid.
 *
 * Interface PaymentGateway TETAP (charge()) — prinsip DIP dipertahankan.
 * charge() di sini membuat sesi Snap dan mengembalikan token sebagai
 * referensi; konfirmasi pembayaran SEBENARNYA datang dari webhook.
 */
class MidtransPaymentGateway implements AsyncPaymentGateway, RefundablePaymentGateway
{
    /**
     * Penuhi kontrak PaymentGateway: buat sesi Snap, kembalikan token.
     * (Untuk alur redirect sungguhan, gunakan createTransaction() yang
     * juga mengembalikan redirect_url.)
     */
    public function charge(int $amount): string
    {
        return $this->snapCreate([
            'transaction_details' => [
                'order_id' => 'MID-'.uniqid(),
                'gross_amount' => $amount,
            ],
        ])['token'];
    }

    /**
     * Buat sesi Snap untuk order nyata — order_id dipakai sebagai korelasi
     * di webhook (Midtrans mengembalikannya apa adanya).
     *
     * @return array{token: string, redirect_url: string}
     */
    public function createTransaction(Order $order): array
    {
        return $this->snapCreate([
            'transaction_details' => [
                'order_id' => (string) $order->getKey(),
                'gross_amount' => $order->total,
            ],
            'customer_details' => [
                'first_name' => $order->user->name,
                'email' => $order->user->email,
            ],
        ]);
    }

    /**
     * Verifikasi signature webhook Midtrans.
     * signature_key = sha512(order_id . status_code . gross_amount . server_key)
     * — constant-time compare, tanpa bocor timing.
     */
    public function verifySignature(string $orderId, string $statusCode, string $grossAmount, string $signature): bool
    {
        $serverKey = (string) config('payment.midtrans.server_key');

        $expected = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        return hash_equals($expected, $signature);
    }

    /**
     * Refund via Midtrans: POST /v2/{id}/refund — Http::fake-able.
     * status_code '200' = provider menerima refund.
     */
    public function refund(string $transactionId, int $amount): bool
    {
        $serverKey = (string) config('payment.midtrans.server_key');

        $response = Http::withBasicAuth($serverKey, '')
            ->post(config('payment.midtrans.api_url')."/v2/{$transactionId}/refund", [
                'refund_amount' => $amount,
                'refund_key' => 'partial-'.uniqid(),
            ]);

        $response->throw();

        return $response->json('status_code') === '200';
    }

    /** POST ke Snap create-transaction; 4xx/5xx dilempar, jangan ditelan. */
    private function snapCreate(array $payload): array
    {
        $response = Http::withBasicAuth(config('payment.midtrans.server_key'), '')
            ->post(config('payment.midtrans.snap_url'), $payload);

        $response->throw();

        return [
            'token' => $response->json('token'),
            'redirect_url' => $response->json('redirect_url'),
        ];
    }
}
