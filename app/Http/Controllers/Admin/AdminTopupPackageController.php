<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TopupPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminTopupPackageController extends Controller
{
    public function index(): View
    {
        return view('admin.topup-packages', ['packages' => TopupPackage::orderBy('coins')->paginate(20)]);
    }

    public function store(Request $request): RedirectResponse
    {
        TopupPackage::create($this->validated($request));

        return redirect()->route('admin.topupPackages.index')->with('success', 'Đã tạo gói nạp xu.');
    }

    public function update(Request $request, TopupPackage $package): RedirectResponse
    {
        $package->update($this->validated($request));

        return back()->with('success', 'Đã cập nhật gói. Đơn cũ giữ nguyên giá đã chốt.');
    }

    public function toggle(TopupPackage $package): RedirectResponse
    {
        $package->update(['is_active' => ! $package->is_active]);

        return back()->with('success', $package->is_active ? 'Đã mở bán gói.' : 'Đã ngừng bán gói.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'coins' => ['required', 'integer', 'min:1', 'max:1000000'],
            'cost_price' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'sell_price' => ['required', 'integer', 'gte:cost_price', 'max:1000000000'],
            'server' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]{1,30}$/D'],
            'napxu_package_id' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'sell_price.gte' => 'Giá bán không được thấp hơn giá gốc Napxu.',
            'server.regex' => 'Mã máy chủ chỉ gồm chữ, số, gạch ngang hoặc gạch dưới.',
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
