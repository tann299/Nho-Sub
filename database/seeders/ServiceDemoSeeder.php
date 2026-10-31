<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Dịch vụ mẫu để xem giao diện khi CHƯA có API key TopSubRe.
 * Giá gốc USD giả định; giá bán = cost × 1.3.
 */
class ServiceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $samples = [
            ['facebook', 'Facebook — Like bài viết', '0.080000', 100, 100000],
            ['facebook', 'Facebook — Follow trang', '0.150000', 500, 500000],
            ['tiktok', 'TikTok — View video', '0.010000', 1000, 10000000],
            ['tiktok', 'TikTok — Like video', '0.060000', 100, 1000000],
            ['instagram', 'Instagram — Follow', '0.120000', 100, 500000],
            ['youtube', 'YouTube — Subscriber', '0.800000', 100, 50000],
            ['youtube', 'YouTube — View video', '0.050000', 1000, 10000000],
        ];

        foreach ($samples as $i => [$platform, $name, $usd, $min, $max]) {
            $category = Category::where('platform', $platform)->first();
            if (! $category) {
                continue;
            }

            $costPrice = (int) floor((float) $usd * 26000);

            Service::firstOrCreate(
                ['provider_service_id' => 'demo-' . ($i + 1)],
                [
                    'category_id' => $category->id,
                    'name' => $name,
                    'description' => 'Dịch vụ mẫu — dữ liệu demo, thay bằng dữ liệu đồng bộ từ TopSubRe.',
                    'cost_usd' => $usd,
                    'cost_price' => $costPrice,
                    'sell_price' => (int) round($costPrice * 1.3),
                    'sell_price_override' => false,
                    'min_qty' => $min,
                    'max_qty' => $max,
                    'speed' => '10K/ngày',
                    'avg_start_time' => '0–30 phút',
                    'is_active' => true,
                    'synced_at' => now(),
                ]
            );
        }
    }
}
