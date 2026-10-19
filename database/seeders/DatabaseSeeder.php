<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Tanpa WithoutModelEvents: hook Product::created mencatat gerakan
     * 'in' stok awal — invariant SUM(inventory_movements) == stock
     * berlaku juga untuk data seed.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Admin panel Filament (/admin) — kontrak seeder utk e2e & G6.
        User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);

        $products = Product::factory(12)->create();

        Order::factory(5)->create()->each(function ($order) use ($products) {
            OrderItem::factory(rand(1, 3))->create([
                'order_id' => $order->id,
                'product_id' => $products->random()->id,
            ]);
        });

        // 1 order PAID utk e2e refund (T5) — status saja; stok sengaja TIDAK
        // dipotong (data test, invariant SUM(movements)==stock tetap terjaga).
        $paid = Order::factory()->create([
            'status' => OrderStatus::Paid,
            'transaction_id' => 'seed-txn-1',
            'paid_at' => now(),
        ]);
        OrderItem::factory(rand(1, 2))->create([
            'order_id' => $paid->id,
            'product_id' => $products->random()->id,
        ]);
    }
}
