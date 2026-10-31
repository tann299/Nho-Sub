<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Service;
use App\Services\PricingService;
use App\Services\ServiceSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminServiceController extends Controller
{
    public function __construct(
        private readonly ServiceSyncService $sync,
        private readonly PricingService $pricing,
    ) {
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $categoryId = (int) $request->query('category_id', 0);

        $services = Service::query()
            ->with('category:id,name,platform')
            ->when($q !== '', fn ($x) => $x->where('name', 'like', "%{$q}%"))
            ->when($categoryId > 0, fn ($x) => $x->where('category_id', $categoryId))
            ->orderBy('category_id')
            ->orderBy('name')
            ->paginate(40)
            ->withQueryString();

        return view('admin.services', [
            'services' => $services,
            'categories' => Category::orderBy('sort_order')->get(['id', 'name']),
            'q' => $q,
            'categoryId' => $categoryId,
        ]);
    }

    public function sync(): RedirectResponse
    {
        try {
            $result = $this->sync->sync();
        } catch (\Throwable $e) {
            return back()->with('error', 'Đồng bộ thất bại: ' . $e->getMessage());
        }

        return back()->with('success', sprintf(
            'Đồng bộ xong: thêm mới %d, cập nhật %d, ẩn %d, tổng %d dịch vụ.',
            $result['created'], $result['updated'], $result['deactivated'], $result['total']
        ));
    }

    /** Sửa giá bán / trạng thái một dịch vụ. Đặt giá riêng sẽ bật cờ override. */
    public function update(Request $request, Service $service): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'sell_price' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'sell_price.required' => 'Vui lòng nhập giá bán.',
            'sell_price.integer' => 'Giá bán phải là số nguyên (VND).',
        ]);

        $newPrice = (int) $data['sell_price'];

        $service->forceFill([
            'name' => $data['name'] ?? $service->name,
            'sell_price' => $newPrice,
            // Giá bán khác giá tự động → coi là admin đặt riêng, đồng bộ sau không ghi đè.
            'sell_price_override' => $newPrice !== $this->pricing->sellPriceFromCost((int) $service->cost_price),
            'is_active' => $request->boolean('is_active'),
        ])->save();

        $message = 'Đã cập nhật dịch vụ.';

        if ($newPrice > 0 && $newPrice <= (int) $service->cost_price) {
            $message .= ' CẢNH BÁO: giá bán không cao hơn giá gốc — bạn đang bán lỗ.';
        }

        return back()->with('success', $message);
    }
}
