<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiktok_accounts', function (Blueprint $table) {
            $table->unique('napxu_account_id');
            $table->softDeletes();
        });
        Schema::table('topup_orders', function (Blueprint $table) {
            $table->uuid('client_token')->nullable()->unique();
            $table->unsignedBigInteger('napxu_order_id')->nullable()->unique();
            $table->string('server', 30)->nullable();
            $table->unsignedBigInteger('napxu_package_id')->nullable();
            $table->unsignedBigInteger('refunded_amount')->default(0);
            $table->string('error_message', 500)->nullable();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('topup_orders', function (Blueprint $table) {
            $table->dropUnique(['client_token']);
            $table->dropUnique(['napxu_order_id']);
            $table->dropIndex(['status', 'created_at']);
            $table->dropColumn(['client_token', 'napxu_order_id', 'server', 'napxu_package_id', 'refunded_amount', 'error_message']);
        });
        Schema::table('tiktok_accounts', function (Blueprint $table) {
            $table->dropUnique(['napxu_account_id']);
            $table->dropSoftDeletes();
        });
    }
};
