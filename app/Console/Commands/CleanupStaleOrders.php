<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Console\Command;

class CleanupStaleOrders extends Command
{
    protected $signature = 'orders:cleanup {--hours=24 : Batas umur order pending (jam)}';

    protected $description = 'Batalkan order pending yang menggantung terlalu lama (gagal bayar/ditinggal)';

    public function handle(): int
    {
        // Per-order (bukan bulk update) karena cancel() = status machine +
        // KEMBALIKAN stok dengan jejak movement — tidak ada stok hilang
        // tanpa jejak saat order menggantung dibatalkan otomatis.
        $stale = Order::query()
            ->where('status', OrderStatus::Pending)
            ->where('created_at', '<', now()->subHours((int) $this->option('hours')))
            ->get();

        $cancelled = 0;

        foreach ($stale as $order) {
            if ($order->cancel('Order pending kedaluwarsa (cleanup)')) {
                $cancelled++;
            }
        }

        $this->info("{$cancelled} order pending dibatalkan.");

        return self::SUCCESS;
    }
}
