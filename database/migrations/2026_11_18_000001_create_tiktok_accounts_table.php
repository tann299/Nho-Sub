<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiktok_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('napxu_account_id');
            $table->string('username', 120);
            $table->string('nickname', 120)->default('');
            $table->string('avatar', 500)->default('');
            $table->unsignedBigInteger('coins')->default(0);
            $table->string('account_status', 30)->default('active');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'napxu_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_accounts');
    }
};
