@extends('layouts.panel')
@section('title', isset($post->id) ? 'Sửa bài viết' : 'Thêm bài viết')
@section('content')
    <div class="card" style="max-width:820px;">
        <h2 class="card-title">{{ isset($post->id) ? 'Sửa bài viết #' . $post->id : 'Thêm bài viết' }}</h2>

        <form method="POST"
              action="{{ isset($post->id) ? route('admin.posts.update', $post) : route('admin.posts.store') }}"
              data-guard>
            @csrf
            <div class="form-group">
                <label class="form-label" for="title">Tiêu đề</label>
                <input class="form-input @error('title') is-invalid @enderror" id="title" name="title"
                       value="{{ old('title', $post->title) }}" required maxlength="200">
            </div>

            <div class="form-group">
                <label class="form-label" for="slug">Slug (URL)</label>
                <input class="form-input @error('slug') is-invalid @enderror" id="slug" name="slug"
                       value="{{ old('slug', $post->slug) }}" placeholder="Để trống sẽ tự sinh từ tiêu đề">
                <div class="form-hint">Chữ thường, số và dấu gạch ngang. Ví dụ: khuyen-mai-thang-11</div>
            </div>

            <div class="form-group">
                <label class="form-label" for="summary">Tóm tắt (hiển thị ở danh sách)</label>
                <textarea class="form-textarea" id="summary" name="summary"
                          style="min-height:70px;">{{ old('summary', $post->summary) }}</textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="body">Nội dung</label>
                <textarea class="form-textarea @error('body') is-invalid @enderror" id="body" name="body"
                          style="min-height:260px;" required>{{ old('body', $post->body) }}</textarea>
                <div class="form-hint">Xuống dòng 2 lần để ngắt đoạn. Xuống dòng đơn sẽ giữ nguyên vị trí.</div>
            </div>

            <div class="form-group">
                <label class="text-sm flex gap-1" style="align-items:center;">
                    <input type="hidden" name="is_published" value="0">
                    <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $post->is_published ?? false))>
                    Đăng bài (hiển thị cho khách)
                </label>
            </div>

            <div class="flex gap-1">
                <button class="btn btn-primary" type="submit">Lưu</button>
                <a href="{{ route('admin.posts.index') }}" class="btn btn-ghost">Huỷ</a>
            </div>
        </form>
    </div>
@endsection
