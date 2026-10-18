<?php

namespace App\Enums;

/**
 * Jenis pergerakan stok. Disimpan sebagai string di kolom `type`,
 * di-cast ke enum di model — sama pola dengan OrderStatus.
 */
enum MovementType: string
{
    case In = 'in';         // stok masuk (restock, pembatalan order)
    case Out = 'out';       // stok keluar (penjualan)
    case Adjust = 'adjust'; // koreksi manual (stock opname)
}
