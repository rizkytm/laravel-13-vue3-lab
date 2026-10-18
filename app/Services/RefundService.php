<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Payment\RefundablePaymentGateway;
use RuntimeException;

/**
 * Koordinasi refund: kembalikan uang lewat gateway, lalu tandai order
 * refunded (yang sekaligus me-restock stok dengan jejak movement).
 *
 * Urutan penting: uang dikembalikan DULU, status diubah SESUDAHNYA.
 * Kalau gateway gagal, status tidak berubah (order tetap paid) — kita
 * tidak pernah menandai refunded tanpa uang benar-benar kembali.
 * Kegagalan gateway dilempar sebagai exception (bukan ditelan).
 */
class RefundService
{
    public function __construct(private RefundablePaymentGateway $gateway) {}

    public function refund(Order $order): bool
    {
        if (! $order->status->canTransitionTo(OrderStatus::Refunded)) {
            return false; // bukan paid → tidak bisa refund
        }

        if (! $order->transaction_id) {
            throw new RuntimeException("Order #{$order->getKey()} tanpa transaction_id — tidak bisa refund.");
        }

        // 1. Uang kembali dulu. false = provider menolak → jangan ubah status.
        if (! $this->gateway->refund($order->transaction_id, $order->total)) {
            return false;
        }

        // 2. Tandai refunded + restock (atomic di model)
        return $order->refund();
    }
}
