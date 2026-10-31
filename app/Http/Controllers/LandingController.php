<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function index(): View
    {
        // Chỉ lấy cột hiển thị công khai — không bao giờ chọn cost_price.
        $priceSamples = Service::query()
            ->where('is_active', true)
            ->orderBy('sell_price')
            ->limit(6)
            ->get(['id', 'name', 'sell_price']);

        return view('landing.index', [
            'priceSamples' => $priceSamples,
            'pricingTiers' => [
                [
                    'name' => 'Khởi đầu',
                    'price' => 'Từ 5.000 ₫ / 1.000',
                    'desc' => 'Phù hợp người mới thử dịch vụ.',
                    'features' => ['Nạp tối thiểu 10.000 ₫', 'Đơn từ 100 lượt', 'Theo dõi trạng thái'],
                    'highlight' => false,
                ],
                [
                    'name' => 'Tăng trưởng',
                    'price' => 'Từ 12.000 ₫ / 1.000',
                    'desc' => 'Dành cho shop đang đẩy bài bán hàng.',
                    'features' => ['Đủ 5 nền tảng', 'Hoàn tiền tự động', 'Ưu tiên hỗ trợ'],
                    'highlight' => true,
                ],
                [
                    'name' => 'Chuyên nghiệp',
                    'price' => 'Liên hệ',
                    'desc' => 'Khối lượng lớn, cần tư vấn riêng.',
                    'features' => ['Báo giá riêng', 'Hỗ trợ 1-1', 'Đối soát hằng tháng'],
                    'highlight' => false,
                ],
            ],
        ]);
    }
}
