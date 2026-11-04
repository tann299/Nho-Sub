<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mã xác thực nạp tiền: mỗi yêu cầu nạp có 1 mã ngẫu nhiên (vd N7K2P9)
 * đưa vào nội dung chuyển khoản → đối chiếu chính xác 1-1,
 * không nhầm giữa 2 khách chuyển cùng lúc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposit_requests', function (Blueprint $table) {
            $table->string('verification_code', 12)->nullable()->after('transfer_code');
            $table->index('verification_code');
        });
    }

    public function down(): void
    {
        Schema::table('deposit_requests', function (Blueprint $table) {
            $table->dropIndex(['verification_code']);
            $table->dropColumn('verification_code');
        });
    }
};
