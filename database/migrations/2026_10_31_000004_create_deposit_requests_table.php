<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Yêu cầu nạp tiền (SRS 7.5) — bổ sung expires_at cho yêu cầu cổng thanh toán. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposit_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate();
            $table->bigInteger('amount');
            $table->string('method', 16)->default('bank');    // bank | payos | momo
            $table->string('transfer_code', 64);
            $table->string('status', 16)->default('pending'); // pending | approved | rejected | expired
            $table->text('admin_note')->nullable();
            $table->string('gateway_txn_id', 128)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']);
            $table->index('method');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_requests');
    }
};
