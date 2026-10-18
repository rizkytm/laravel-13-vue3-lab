<?php

namespace App\Enums;

/**
 * Status order sebagai ENUM, bukan string mentah.
 * Cast di model → akses $order->status mengembalikan instance enum,
 * bukan string — typo 'pendng' jadi error, bukan data korup.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Failed = 'failed'; // payment job gagal total ($tries habis)

    /**
     * Status machine: daftar transisi yang SAH per status.
     * Semua perubahan status wajib melewati pengecekan ini (Order::transitionTo)
     * — tidak ada "lompatan" sembunyi-sembunyi di controller/job/command.
     *
     * - pending → paid      : pembayaran sukses (ProcessPayment)
     * - pending → cancelled : customer batal / cleanup order menggantung
     * - pending → failed    : payment job gagal total
     * - paid → *            : TIDAK ADA — refund (tahap 5) menambah transisi nanti
     */
    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Pending => [self::Paid, self::Cancelled, self::Failed],
            default => [],
        }, true);
    }
}
