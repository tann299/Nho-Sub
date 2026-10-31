<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đơn hàng (SRS 7.4).
 * - client_token: chống double-submit ở tầng DB (UNIQUE).
 * - refunded_amount/refund_count: kiểm soát hoàn tiền nhiều đợt, không hoàn trùng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate();
            $table->foreignId('service_id')->constrained('services')->cascadeOnUpdate();
            $table->uuid('client_token')->unique();
            $table->string('provider_order_id', 128)->nullable();
            $table->text('target_link');
            $table->integer('quantity');
            $table->bigInteger('unit_price');                 // VND / 1000, chốt tại thời điểm đặt
            $table->bigInteger('total_amount');               // VND đã trừ của khách
            $table->bigInteger('cost_amount');                // VND giá gốc phải trả NCC
            $table->string('status', 24)->default('pending');
            $table->integer('start_count')->nullable();
            $table->integer('remains')->nullable();
            $table->bigInteger('refunded_amount')->default(0);
            $table->unsignedInteger('refund_count')->default(0);
            $table->string('error_message', 500)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->unsignedInteger('sync_attempts')->default(0);
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('provider_order_id');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
