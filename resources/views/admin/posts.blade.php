@extends('layouts.panel')
@section('title', 'Quản lý bài viết')
@section('content')
    <div class="flex-between mb-2">
        <h2 style="font-size:18px;margin:0;">Bài viết & thông báo</h2>
        <a href="{{ route('admin.posts.create') }}" class="btn btn-primary">Thêm bài viết</a>
    </div>

    <div class="card" style="padding:0;overflow:hidden;">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>#</th><th>Tiêu đề</th><th>Trạng thái</th><th>Ngày đăng</th><th></th></tr>
                </thead>
                <tbody>
                @forelse ($posts as $post)
                    <tr>
                        <td>{{ $post->id }}</td>
                        <td>
                            <strong>{{ $post->title }}</strong>
                            <div class="text-sm text-muted mono">{{ $post->slug }}</div>
                        </td>
                        <td>
                            <span class="badge {{ $post->is_published ? 'badge-completed' : 'badge-neutral' }}">
                                {{ $post->is_published ? 'Đã đăng' : 'Nháp' }}
                            </span>
                        </td>
                        <td class="text-sm text-muted">{{ $post->published_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="nowrap">
                            <a class="btn btn-ghost btn-sm" href="{{ route('admin.posts.edit', $post) }}">Sửa</a>
                            <form method="POST" action="{{ route('admin.posts.destroy', $post) }}" style="display:inline;"
                                  data-confirm="Xoá bài viết này?">
                                @csrf
                                <button class="btn btn-sm btn-danger" type="submit">Xoá</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted" style="padding:40px;">Chưa có bài viết nào.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $posts->links() }}</div>
@endsection
