<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dịch vụ đồng bộ từ TopSubRe (SRS 7.3).
 * Giá: cost_usd giữ nguyên USD gốc; cost_price/sell_price quy về VND cho 1000 lượt (BIGINT).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnUpdate();
            $table->string('provider_service_id', 64)->unique();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->decimal('cost_usd', 12, 6)->default(0);   // giá gốc USD / 1000
            $table->bigInteger('cost_price')->default(0);     // VND / 1000
            $table->bigInteger('sell_price')->default(0);     // VND / 1000
            $table->boolean('sell_price_override')->default(false);
            $table->integer('min_qty')->default(1);
            $table->integer('max_qty')->default(1000000);
            $table->string('speed', 120)->nullable();
            $table->string('avg_start_time', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['category_id', 'is_active']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
