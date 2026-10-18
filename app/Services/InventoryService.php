<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * SATU-satunya jalan untuk mengubah stok. Setiap perubahan menulis
 * inventory_movements (jejak audit) DAN mengupdate products.stock dalam
 * transaksi yang sama — stok tidak pernah berubah tanpa jejak.
 *
 * Invariant yang dijaga: SUM(inventory_movements.quantity) per produk
 * == products.stock (kolom turunan untuk query cepat).
 */
class InventoryService
{
    /**
     * Kurangi stok (penjualan). lockForUpdate: baca-lock-tulis dalam satu
     * transaksi — dua checkout paralel tidak bisa oversell.
     *
     * @return bool false bila stok tidak mencukupi (tanpa perubahan apa pun)
     */
    public function decrement(Product $product, int $quantity, string $reason, ?Order $order = null): bool
    {
        return DB::transaction(function () use ($product, $quantity, $reason, $order): bool {
            $stock = Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->value('stock');

            if ($stock < $quantity) {
                return false;
            }

            $newStock = $stock - $quantity;

            Product::query()->whereKey($product->getKey())->update(['stock' => $newStock]);
            $product->refresh();

            $this->write($product, MovementType::Out, -$quantity, $newStock, $reason, $order);

            return true;
        });
    }

    /**
     * Tambah stok (restock, pembatalan order). Quantity selalu positif.
     */
    public function increment(Product $product, int $quantity, string $reason, ?Order $order = null): InventoryMovement
    {
        return DB::transaction(function () use ($product, $quantity, $reason, $order): InventoryMovement {
            $stock = Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->value('stock');

            $newStock = $stock + $quantity;

            Product::query()->whereKey($product->getKey())->update(['stock' => $newStock]);
            $product->refresh();

            return $this->write($product, MovementType::In, $quantity, $newStock, $reason, $order);
        });
    }

    /**
     * Koreksi ke nilai ABSOLUT (stock opname). Tercatat sebagai 'adjust'
     * dengan delta + stock_after — riwayat tetap konsisten.
     */
    public function adjust(Product $product, int $newStock, string $reason): InventoryMovement
    {
        return DB::transaction(function () use ($product, $newStock, $reason): InventoryMovement {
            $stock = Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->value('stock');

            Product::query()->whereKey($product->getKey())->update(['stock' => $newStock]);
            $product->refresh();

            return $this->write($product, MovementType::Adjust, $newStock - $stock, $newStock, $reason, null);
        });
    }

    /** Tulis jejak movement — dipanggil DALAM transaksi pemanggil. */
    private function write(
        Product $product,
        MovementType $type,
        int $quantity,
        int $stockAfter,
        string $reason,
        ?Order $order,
    ): InventoryMovement {
        return InventoryMovement::create([
            'product_id' => $product->getKey(),
            'order_id' => $order?->getKey(),
            'type' => $type,
            'quantity' => $quantity,
            'stock_after' => $stockAfter,
            'reason' => $reason,
        ]);
    }
}
