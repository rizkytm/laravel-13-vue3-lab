<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Nullable: restock/opname tidak terkait order
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 10); // in | out | adjust (MovementType)
            // SIGNED delta: positif = masuk, negatif = keluar.
            // Invariant: SUM(quantity) per produk == products.stock
            $table->integer('quantity');
            // Snapshot stok SESUDAH gerakan — jejak audit tanpa harus
            // menjumlah ulang seluruh riwayat
            $table->unsignedInteger('stock_after');
            $table->string('reason');
            $table->timestamps();

            // Riwayat per produk (tampilan admin + reconciliation) — terurut waktu
            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
