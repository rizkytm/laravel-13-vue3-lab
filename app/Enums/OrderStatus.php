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
}
