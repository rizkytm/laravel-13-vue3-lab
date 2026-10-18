<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Payment\MidtransPaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Webhook pembayaran Midtrans — DIVERIFIKASI SIGNATURE, tanpa auth session.
 * Endpoint publik karena dipanggil server Midtrans, tapi AMAN karena
 * signature_key membuktikan payload asli (sha512 order_id+status_code+
 * gross_amount+server_key). Tanpa signature valid → tolak mentah.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        MidtransPaymentGateway $gateway,
    ): Response {
        $payload = $request->all();

        // 1. Signature check SEBELUM sentuh database apa pun
        $valid = $gateway->verifySignature(
            (string) ($payload['order_id'] ?? ''),
            (string) ($payload['status_code'] ?? ''),
            (string) ($payload['gross_amount'] ?? ''),
            (string) ($payload['signature_key'] ?? ''),
        );

        if (! $valid) {
            abort(response()->json(['message' => 'Signature tidak valid.'], 403));
        }

        $order = Order::query()->find($payload['order_id'] ?? '');

        if (! $order) {
            abort(response()->json(['message' => 'Order tidak ditemukan.'], 404));
        }

        // 2. Map status Midtrans → status machine kami
        $target = match ($payload['transaction_status'] ?? '') {
            'settlement', 'capture' => OrderStatus::Paid,
            'cancel' => OrderStatus::Cancelled,
            'deny', 'failure', 'expire' => OrderStatus::Failed,
            default => null, // 'pending' dsb — tidak ada transisi
        };

        // 3. Lewati machine — idempotent (webhook bisa dikirim ulang) dan
        //    transisi tidak sah (mis. paid→paid ulang) dibiarkan tanpa error
        if ($target !== null) {
            $order->transitionTo($target, [
                'transaction_id' => $payload['transaction_id'] ?? null,
                'paid_at' => $target === OrderStatus::Paid ? now() : null,
            ]);
        }

        return response('', 200);
    }
}
