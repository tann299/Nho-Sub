<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Liên kết yêu cầu nạp với mã đơn PayOS. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payos_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deposit_request_id')->constrained('deposit_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate();
            $table->unsignedBigInteger('order_code')->unique();
            $table->string('payment_link_id', 64)->nullable();
            $table->string('checkout_url', 500)->nullable();
            $table->string('qr_code', 1000)->nullable();
            $table->string('status', 16)->default('pending'); // pending | paid | cancelled | expired
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payos_transactions');
    }
};
