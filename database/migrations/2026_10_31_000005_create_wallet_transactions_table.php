<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sổ cái ví (SRS 7.6).
 * idempotency_key UNIQUE là trụ cột chống cộng/trừ trùng của toàn hệ thống.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('deposit_id')->nullable()->constrained('deposit_requests')->nullOnDelete();
            $table->string('type', 16);                       // deposit | order_charge | refund | adjust
            $table->bigInteger('amount');                     // dương = cộng, âm = trừ
            $table->bigInteger('balance_after');
            $table->string('idempotency_key', 128)->unique();
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
