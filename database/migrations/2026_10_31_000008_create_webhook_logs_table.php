<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Nhật ký webhook thanh toán — dedupe theo (gateway, gateway_txn_id). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 16);                    // sepay | payos
            $table->string('gateway_txn_id', 128);
            $table->bigInteger('amount')->default(0);
            $table->string('content', 255)->nullable();
            $table->string('status', 24);                     // processed | duplicate | unmatched | rejected | processed_late
            $table->string('reason', 255)->nullable();
            $table->json('payload')->nullable();              // đã che key trước khi lưu
            $table->timestamps();

            $table->unique(['gateway', 'gateway_txn_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
