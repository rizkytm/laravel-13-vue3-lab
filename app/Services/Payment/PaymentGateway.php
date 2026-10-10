<?php

namespace App\Services\Payment;

/**
 * Kontrak pembayaran. Controller/service bergantung ke INTERFACE ini,
 * bukan ke implementasi — prinsip Dependency Inversion (DIP di SOLID).
 */
interface PaymentGateway
{
    /**
     * Proses pembayaran, kembalikan id transaksi.
     */
    public function charge(int $amount): string;
}
