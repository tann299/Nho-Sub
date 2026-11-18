<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topup_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->unsignedBigInteger('coins');
            $table->unsignedBigInteger('cost_price');   // VND napxu thu theo gói
            $table->unsignedBigInteger('sell_price');   // VND khách trả
            $table->string('server', 30)->default('server1');
            $table->unsignedBigInteger('napxu_package_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topup_packages');
    }
};
