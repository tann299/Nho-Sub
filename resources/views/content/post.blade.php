@extends(auth()->check() ? 'layouts.panel' : 'layouts.landing')
@section('title', $post->title)
@section('content')
    @if (auth()->check())
        <div style="max-width:820px;">
    @else
        <section class="section"><div class="container"><div style="max-width:820px;">
    @endif

    <a href="{{ route('content.posts') }}" class="text-sm link-underline">← Tất cả bài viết</a>

    <div class="card mt-2">
        <h1 style="font-size:24px;">{{ $post->title }}</h1>
        <div class="text-sm text-muted mb-3">{{ $post->published_at?->format('d/m/Y H:i') }}</div>

        @if ($post->summary)
            <div class="alert alert-info">{{ $post->summary }}</div>
        @endif

        <div style="line-height:1.8;">
            {!! nl2br(e($post->body)) !!}
        </div>
    </div>

    @if (! auth()->check())
    </div></div></section>
    @else
    </div>
    @endif
@endsection
