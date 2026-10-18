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
    case Refunded = 'refunded'; // uang kembali setelah paid (tahap 5)

    /**
     * Status machine: daftar transisi yang SAH per status.
     * Semua perubahan status wajib melewati pengecekan ini (Order::transitionTo)
     * — tidak ada "lompatan" sembunyi-sembunyi di controller/job/command.
     *
     * - pending → paid      : pembayaran sukses (ProcessPayment / webhook)
     * - pending → cancelled : customer batal / cleanup order menggantung
     * - pending → failed    : payment job gagal total
     * - paid → refunded     : refund (tahap 5) — restock + jejak movement
     * - terminal (cancelled/failed/refunded) → tanpa keluar
     */
    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Pending => [self::Paid, self::Cancelled, self::Failed],
            self::Paid => [self::Refunded],
            default => [],
        }, true);
    }
}
