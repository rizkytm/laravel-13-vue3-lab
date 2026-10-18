<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Payment\PaymentGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Charge payment di background. Checkout tidak menunggu gateway —
 * respons cepat, kegagalan ditangani retry, bukan memblokir user.
 */
class ProcessPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Total percobaan sebelum failed() dipanggil */
    public int $tries = 3;

    /** Jeda antar retry (detik): 10s, 30s, 60s — backoff naik biar gak menumpuk */
    public array $backoff = [10, 30, 60];

    public function __construct(public Order $order) {}

    public function handle(PaymentGateway $gateway): void
    {
        // IDEMPOTENCY: retry setelah sukses (exception SETELAH charge
        // sebelum update) tidak boleh menagih dua kali. SerializesModels
        // → tiap retry model di-refetch ulang, paid_at dari attempt
        // sebelumnya terbaca.
        if ($this->order->paid_at !== null) {
            return;
        }

        $transactionId = $gateway->charge($this->order->total);

        // Lewat status machine — pending → paid satu-satunya jalan yang sah
        // (transisi lain mengembalikan false dan status tidak berubah).
        $this->order->transitionTo(OrderStatus::Paid, [
            'transaction_id' => $transactionId,
            'paid_at' => now(),
        ]);
    }

    /**
     * Dipanggil otomatis setelah $tries habis semua gagal.
     * Tandai status supaya user/ops melihat order bukan "pending" selamanya.
     */
    public function failed(?Throwable $exception): void
    {
        $this->order->transitionTo(OrderStatus::Failed);

        Log::error('Payment permanently failed', [
            'order_id' => $this->order->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
