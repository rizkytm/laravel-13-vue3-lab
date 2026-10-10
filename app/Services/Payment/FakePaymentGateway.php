<?php

namespace App\Services\Payment;

/**
 * Implementasi "palsu": gak ada HTTP call ke payment provider beneran.
 * Dipakai di development & test — nanti diganti implementasi asli dengan
 * mengubah SATU baris binding di AppServiceProvider, client gak diubah.
 */
class FakePaymentGateway implements PaymentGateway
{
    /** @var array<int, int> log nominal yang berhasil dicharge */
    public array $charges = [];

    public function charge(int $amount): string
    {
        $this->charges[] = $amount;

        return 'txn_'.count($this->charges);
    }
}
