<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use Illuminate\View\View;

/**
 * Nội dung công khai: bài viết, điều khoản, FAQ (tham khảo menu TopSubRe).
 */
class ContentController extends Controller
{
    /** Danh sách bài viết đã đăng. */
    public function posts(): View
    {
        return view('content.posts', [
            'posts' => Post::published()->paginate(10),
        ]);
    }

    /** Chi tiết một bài viết theo slug — chỉ bài đã đăng. */
    public function post(string $slug): View
    {
        $post = Post::published()->where('slug', $slug)->firstOrFail();

        return view('content.post', [
            'post' => $post,
        ]);
    }

    /** Điều khoản sử dụng — trang tĩnh. */
    public function terms(): View
    {
        return view('content.terms');
    }

    /** Câu hỏi thường gặp — trang tĩnh. */
    public function faq(): View
    {
        return view('content.faq');
    }

    /** Danh mục theo nền tảng dùng cho submenu (gọi từ view composer). */
    public static function menuCategories()
    {
        return Category::query()
            ->where('is_active', true)
            ->whereHas('services', fn ($q) => $q->where('is_active', true))
            ->with('services:id,category_id')
            ->orderBy('platform')
            ->orderBy('sort_order')
            ->get(['id', 'name', 'platform']);
    }
}
