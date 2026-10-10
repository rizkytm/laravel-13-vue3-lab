<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Idempotency guard: job retry mengecek paid_at sebelum charge ulang
            $table->string('transaction_id')->nullable()->after('total');
            $table->timestamp('paid_at')->nullable()->after('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['transaction_id', 'paid_at']);
        });
    }
};
