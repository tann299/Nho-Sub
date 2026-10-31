<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sổ hoàn tiền theo từng đợt — mỗi lần hoàn là 1 dòng (seq tăng dần).
 * UNIQUE(order_id, seq) bảo đảm cron chạy chồng nhau không ghi trùng đợt.
 *
 * Ruling (pre-flight #4): đặt sau wallet_transactions để FK hợp lệ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedInteger('seq');
            $table->bigInteger('amount');
            $table->string('reason', 32);                     // auto_partial | auto_canceled | provider_error | admin_manual
            $table->foreignId('wallet_transaction_id')->nullable()
                  ->constrained('wallet_transactions')->nullOnDelete();
            $table->timestamps();

            $table->unique(['order_id', 'seq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_refunds');
    }
};
