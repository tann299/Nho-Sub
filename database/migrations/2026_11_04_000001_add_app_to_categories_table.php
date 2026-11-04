<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm cột `app` cho categories: app cụ thể trong nền tảng "khác"
 * (twitter, shopee, threads, kwai...) để gắn logo đúng trên menu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('app', 32)->default('')->after('platform');
        });

        // Nhận diện app từ tên danh mục: "X - Twitter Tăng Like" → twitter.
        $map = [
            'x' => 'twitter', 'twitter' => 'twitter',
            'shopee' => 'shopee',
            'threads' => 'threads',
            'kwai' => 'kwai',
            'soundcloud' => 'soundcloud',
            'lazada' => 'shopee',       // sàn TMĐT — dùng logo shopee chung
            'traffic' => 'traffic',
            'google' => 'traffic',
        ];

        $rows = DB::table('categories')->where('platform', 'khac')->get(['id', 'name']);

        foreach ($rows as $row) {
            $first = mb_strtolower(trim(explode(' ', trim($row->name))[0]));

            foreach ($map as $needle => $app) {
                if (str_starts_with($first, $needle)) {
                    DB::table('categories')->where('id', $row->id)->update(['app' => $app]);
                    break;
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('app');
        });
    }
};
