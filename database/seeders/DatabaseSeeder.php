<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $products = Product::factory(12)->create();

        Order::factory(5)->create()->each(function ($order) use ($products) {
            OrderItem::factory(rand(1, 3))->create([
                'order_id' => $order->id,
                'product_id' => $products->random()->id,
            ]);
        });
    }
}
