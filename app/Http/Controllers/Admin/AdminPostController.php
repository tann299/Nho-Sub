<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Quản lý bài viết/thông báo — CRUD tối giản. */
class AdminPostController extends Controller
{
    public function index(): View
    {
        return view('admin.posts', [
            'posts' => Post::latest('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.post-form', ['post' => new Post]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePost($request);

        Post::create($this->buildAttributes($data));

        return redirect()->route('admin.posts.index')
            ->with('success', 'Đã tạo bài viết.');
    }

    public function edit(Post $post): View
    {
        return view('admin.post-form', ['post' => $post]);
    }

    /** Ghim / bỏ ghim bài viết lên đầu trang chủ. Chỉ tác động bài đã đăng. */
    public function pin(Request $request, Post $post): RedirectResponse
    {
        if (! $post->is_published) {
            return back()->with('error', 'Chỉ ghim được bài đã đăng. Vui lòng đăng bài trước.');
        }

        $post->update(['is_pinned' => ! $post->is_pinned]);

        return back()->with('success', $post->is_pinned
            ? 'Đã ghim bài viết lên đầu trang chủ.'
            : 'Đã bỏ ghim bài viết.');
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $data = $this->validatePost($request);

        $post->update($this->buildAttributes($data, $post));

        return redirect()->route('admin.posts.index')
            ->with('success', 'Đã cập nhật bài viết.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        $post->delete();

        return back()->with('success', 'Đã xoá bài viết.');
    }

    private function validatePost(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:220', 'regex:/^[a-z0-9\-]+$/'],
            'summary' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:50000'],
            'is_published' => ['nullable', 'boolean'],
            'is_pinned' => ['nullable', 'boolean'],
        ], [
            'title.required' => 'Vui lòng nhập tiêu đề.',
            'body.required' => 'Vui lòng nhập nội dung.',
            'slug.regex' => 'Slug chỉ gồm chữ thường, số và dấu gạch ngang.',
        ]);
    }

    /** Chuẩn hoá slug + thời gian đăng. */
    private function buildAttributes(array $data, ?Post $existing = null): array
    {
        $slug = trim((string) ($data['slug'] ?? ''));

        if ($slug === '') {
            $slug = Str::slug($data['title']);

            // Đảm bảo slug duy nhất (trừ chính bài đang sửa).
            $base = $slug;
            $i = 1;
            while (Post::where('slug', $slug)->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))->exists()) {
                $slug = $base . '-' . (++$i);
            }
        }

        $isPublished = (bool) ($data['is_published'] ?? false);

        return [
            'title' => $data['title'],
            'slug' => $slug,
            'summary' => $data['summary'] ?? null,
            'body' => $data['body'],
            'is_published' => $isPublished,
            // Ghim chỉ có nghĩa khi bài đã đăng
            'is_pinned' => $isPublished && (bool) ($data['is_pinned'] ?? false),
            // Bật đăng lần đầu → gắn thời điểm; đã đăng rồi thì giữ nguyên.
            'published_at' => $isPublished
                ? ($existing?->published_at ?? now())
                : ($existing?->published_at),
        ];
    }
}
