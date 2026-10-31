<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceCatalogController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $platform = (string) $request->query('platform', '');

        $categories = Category::query()
            ->where('is_active', true)
            ->with(['services' => function ($query) use ($q) {
                // Chỉ trả về cột cần hiển thị — KHÔNG bao giờ chọn cost_price.
                $query->select([
                    'id', 'category_id', 'name', 'description', 'sell_price',
                    'min_qty', 'max_qty', 'speed', 'avg_start_time',
                ])
                    ->where('is_active', true)
                    ->when($q !== '', fn ($qq) => $qq->where('name', 'like', '%' . $q . '%'))
                    ->orderBy('sell_price');
            }])
            ->when($platform !== '', fn ($c) => $c->where('platform', $platform))
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Category $c) => $c->services->isNotEmpty())
            ->values();

        return view('panel.services', [
            'categories' => $categories,
            'q' => $q,
            'platform' => $platform,
            'platforms' => Category::where('is_active', true)->distinct()->pluck('platform'),
        ]);
    }
}
