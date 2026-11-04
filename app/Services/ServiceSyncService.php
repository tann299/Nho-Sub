<?php

namespace App\Services;

use App\Models\ApiSyncLog;
use App\Models\Category;
use App\Models\Service;
use App\Providers\ProviderManager;
use Illuminate\Support\Facades\DB;

/**
 * Đồng bộ dịch vụ từ nhà cung cấp.
 *
 * Quy tắc:
 *  - Đối chiếu theo provider_service_id: có thì cập nhật, chưa có thì tạo.
 *  - Luôn cập nhật cost_usd + cost_price.
 *  - sell_price: chỉ tính lại theo % nếu admin CHƯA đặt giá riêng.
 *  - Dịch vụ biến mất khỏi NCC: is_active = 0, KHÔNG xoá (đơn cũ còn tra cứu).
 *  - Lỗi API: không động vào dữ liệu cũ.
 */
class ServiceSyncService
{
    public function __construct(
        private readonly ProviderManager $providers,
        private readonly PricingService $pricing,
    ) {
    }

    public function sync(): array
    {
        $remote = $this->providers->driver()->services();

        $created = 0;
        $updated = 0;
        $deactivated = 0;
        $seenIds = [];

        DB::transaction(function () use ($remote, &$created, &$updated, &$deactivated, &$seenIds) {
            foreach ($remote as $item) {
                $seenIds[] = $item->id;

                $category = $this->resolveCategory($item->category);
                $costPrice = $this->pricing->costPriceFromUsd($item->costUsd);

                $service = Service::where('provider_service_id', $item->id)->first();

                if ($service === null) {
                    Service::create([
                        'category_id' => $category->id,
                        'provider_service_id' => $item->id,
                        'name' => mb_substr($item->name, 0, 255),
                        'description' => $item->description,
                        'cost_usd' => $item->costUsd,
                        'cost_price' => $costPrice,
                        'sell_price' => $this->pricing->sellPriceFromCost($costPrice),
                        'sell_price_override' => false,
                        'min_qty' => max(1, $item->minQty),
                        'max_qty' => max($item->minQty, $item->maxQty),
                        'is_active' => true,
                        'synced_at' => now(),
                    ]);

                    $created++;
                    continue;
                }

                $service->forceFill([
                    'category_id' => $category->id,
                    'name' => mb_substr($item->name, 0, 255),
                    'description' => $item->description,
                    'cost_usd' => $item->costUsd,
                    'cost_price' => $costPrice,
                    'sell_price' => $service->sell_price_override
                        ? $service->sell_price
                        : $this->pricing->sellPriceFromCost($costPrice),
                    'min_qty' => max(1, $item->minQty),
                    'max_qty' => max($item->minQty, $item->maxQty),
                    'is_active' => true,
                    'synced_at' => now(),
                ])->save();

                $updated++;
            }

            // Dịch vụ không còn trong danh sách NCC → ẩn, không xoá.
            $deactivated = Service::whereNotIn('provider_service_id', $seenIds)
                ->where('is_active', true)
                ->update(['is_active' => false, 'updated_at' => now()]);
        });

        ApiSyncLog::create([
            'action' => 'sync_services',
            'order_id' => null,
            'request_summary' => 'Đồng bộ danh sách dịch vụ',
            'response_summary' => "Tạo mới {$created}, cập nhật {$updated}, ẩn {$deactivated}, tổng " . count($remote),
            'success' => true,
            'created_at' => now(),
        ]);

        return [
            'created' => $created,
            'updated' => $updated,
            'deactivated' => $deactivated,
            'total' => count($remote),
        ];
    }

    /** Tìm hoặc tạo danh mục theo tên NCC trả về; đoán nền tảng + app từ tên. */
    private function resolveCategory(string $name): Category
    {
        $name = trim($name) !== '' ? mb_substr(trim($name), 0, 160) : 'Khác';

        $category = Category::where('name', $name)->first();

        if ($category !== null) {
            return $category;
        }

        $lower = mb_strtolower($name);
        $platform = match (true) {
            str_contains($lower, 'facebook'), str_contains($lower, 'fb') => 'facebook',
            str_contains($lower, 'tiktok') => 'tiktok',
            str_contains($lower, 'instagram'), str_contains($lower, 'ig') => 'instagram',
            str_contains($lower, 'youtube'), str_contains($lower, 'yt') => 'youtube',
            default => 'khac',
        };

        // App cụ thể trong nền tảng "khác" — để menu gắn đúng logo.
        $app = '';
        if ($platform === 'khac') {
            $first = mb_strtolower(explode(' ', trim($name))[0]);
            $app = match (true) {
                str_starts_with($first, 'x'), str_starts_with($first, 'twitter') => 'twitter',
                str_starts_with($first, 'shopee'), str_starts_with($first, 'lazada') => 'shopee',
                str_starts_with($first, 'threads') => 'threads',
                str_starts_with($first, 'kwai') => 'kwai',
                str_starts_with($first, 'soundcloud') => 'soundcloud',
                str_starts_with($first, 'traffic'), str_starts_with($first, 'google') => 'traffic',
                default => '',
            };
        }

        return Category::create([
            'name' => $name,
            'platform' => $platform,
            'app' => $app,
            'sort_order' => 100,
            'is_active' => true,
        ]);
    }
}
