<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topup_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tiktok_account_id')->constrained('tiktok_accounts')->cascadeOnDelete();
            $table->foreignId('topup_package_id')->constrained('topup_packages')->restrictOnDelete();
            $table->unsignedBigInteger('coins');
            $table->unsignedBigInteger('sell_price');
            $table->unsignedBigInteger('cost_price');
            $table->string('napxu_order_ref', 64)->nullable()->index();
            $table->string('status', 20)->default('pending');
            $table->timestamp('synced_at')->nullable();
            $table->unsignedInteger('synced_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topup_orders');
    }
};
