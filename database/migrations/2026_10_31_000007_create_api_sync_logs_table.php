<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Nhật ký gọi API nhà cung cấp (SRS 7.8) — không bao giờ chứa API key. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 32);                     // sync_services | add_order | check_status | check_balance
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->text('request_summary')->nullable();
            $table->text('response_summary')->nullable();
            $table->boolean('success')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->index('order_id');
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_sync_logs');
    }
};
