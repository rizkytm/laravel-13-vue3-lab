<?php

namespace App\Services\Payment;

/**
 * Kontrak refund — opsional di atas PaymentGateway.
 * Driver yang MENDUKUNG refund mengimplementasi interface ini; caller
 * mengecek `instanceof RefundablePaymentGateway` sebelum memanggil
 * (prinsip Interface Segregation — tidak semua gateway wajib refund).
 */
interface RefundablePaymentGateway extends PaymentGateway
{
    /**
     * Kembalikan dana untuk transaksi tertentu. Return true bila provider
     * menerima permintaan refund (sukses di sisi gateway).
     */
    public function refund(string $transactionId, int $amount): bool;
}
