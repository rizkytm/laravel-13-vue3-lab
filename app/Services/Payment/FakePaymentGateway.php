<?php

namespace App\Services\Payment;

/**
 * Implementasi "palsu": gak ada HTTP call ke payment provider beneran.
 * Dipakai di development & test — nanti diganti implementasi asli dengan
 * mengubah SATU baris binding di AppServiceProvider, client gak diubah.
 */
class FakePaymentGateway implements RefundablePaymentGateway
{
    /** @var array<int, int> log nominal yang berhasil dicharge */
    public array $charges = [];

    /** @var array<int, string> log transaction_id yang berhasil di-refund */
    public array $refunds = [];

    public function charge(int $amount): string
    {
        $this->charges[] = $amount;

        return 'txn_'.count($this->charges);
    }

    public function refund(string $transactionId, int $amount): bool
    {
        $this->refunds[] = $transactionId;

        return true;
    }
}
