@extends(auth()->check() ? 'layouts.panel' : 'layouts.landing')
@section('title', 'Bài viết & thông báo')
@section('content')
    @if (auth()->check())
        <h2 style="font-size:20px;" class="mb-2">Bài viết & thông báo</h2>
    @else
        <section class="section">
            <div class="container">
    @endif

    <div class="{{ auth()->check() ? '' : 'grid grid-3' }}" style="{{ auth()->check() ? 'max-width:820px;' : '' }}">
        @forelse ($posts as $post)
            <article class="card card-hover mb-2">
                <h3 style="font-size:17px;">
                    <a href="{{ route('content.post', $post->slug) }}" class="link-underline" style="color:var(--text);">
                        {{ $post->title }}
                    </a>
                </h3>
                @if ($post->summary)
                    <p class="text-sm text-muted">{{ $post->summary }}</p>
                @endif
                <div class="text-sm text-muted">{{ $post->published_at?->format('d/m/Y H:i') }}</div>
            </article>
        @empty
            <div class="card empty-state">Chưa có bài viết nào.</div>
        @endforelse
    </div>

    <div class="mt-3">{{ $posts->links() }}</div>

    @if (! auth()->check())
            </div>
        </section>
    @endif
@endsection
