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
        // Bulk update — hemat query. Event model tidak fire per baris,
        // di sini memang tidak butuh.
        $cancelled = Order::query()
            ->where('status', OrderStatus::Pending)
            ->where('created_at', '<', now()->subHours((int) $this->option('hours')))
            ->update(['status' => OrderStatus::Cancelled]);

        $this->info("{$cancelled} order pending dibatalkan.");

        return self::SUCCESS;
    }
}
