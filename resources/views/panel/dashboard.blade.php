@extends('layouts.panel')
@section('title', 'Tổng quan')
@section('content')
    {{-- Banner thông báo mới nhất (admin đăng bài → hiện ngay tại đây) --}}
    @if ($latestPosts->isNotEmpty())
        <div class="page-hero" style="background:var(--surface-deep);border-color:var(--surface-deep);">
            <div class="page-hero-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
            </div>
            <div class="page-hero-body">
                <h2 class="page-hero-title" style="color:#fff;">Thông báo mới</h2>
                @foreach ($latestPosts as $post)
                    <p style="margin:3px 0 0;font-size:13px;">
                        <a href="{{ route('content.post', $post->slug) }}"
                           style="color:rgba(255,255,255,.92);text-decoration:underline;">{{ $post->title }}</a>
                        <span style="color:rgba(255,255,255,.55);font-size:11.5px;"> · {{ $post->published_at?->format('d/m/Y') }}</span>
                    </p>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid grid-4 mb-3">
        <div class="stat">
            <div class="stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 7a2 2 0 0 1 2-2h13a1 1 0 0 1 1 1v3"/><path d="M3 7v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H5"/><circle cx="17" cy="14" r="1"/></svg>
            </div>
            <div class="stat-body">
                <p class="stat-label">Số dư ví</p>
                <div class="stat-value primary">{{ number_format($stats['balance'], 0, ',', '.') }} ₫</div>
            </div>
        </div>
        <div class="stat">
            <div class="stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2"/></svg>
            </div>
            <div class="stat-body">
                <p class="stat-label">Tổng số đơn</p>
                <div class="stat-value">{{ number_format($stats['total_orders'], 0, ',', '.') }}</div>
            </div>
        </div>
        <div class="stat">
            <div class="stat-icon warning">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
            </div>
            <div class="stat-body">
                <p class="stat-label">Đơn đang chạy</p>
                <div class="stat-value warning">{{ number_format($stats['running_orders'], 0, ',', '.') }}</div>
            </div>
        </div>
        <div class="stat">
            <div class="stat-icon success">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
            </div>
            <div class="stat-body">
                <p class="stat-label">Thông báo mới</p>
                <div class="stat-value">{{ $notifications->whereNull('read_at')->count() }}</div>
            </div>
        </div>
    </div>

    <div class="grid grid-2">
        <div class="card">
            <div class="flex-between mb-2">
                <h3 class="card-title mb-0">Đơn gần đây</h3>
                <a href="{{ route('panel.orders.index') }}" class="text-sm link-underline">Xem tất cả</a>
            </div>

            @forelse ($recentOrders as $order)
                <div class="flex-between" style="padding:12px 0;border-bottom:1px solid #EDF2FA;">
                    <div style="min-width:0;">
                        <a href="{{ route('panel.orders.show', $order) }}" class="link-underline" style="font-weight:600;">
                            #{{ $order->id }}
                        </a>
                        <div class="text-sm text-muted truncate">{{ $order->service->name ?? '—' }}</div>
                    </div>
                    <div class="text-right nowrap">
                        @include('partials.order-status', ['status' => $order->status])
                        <div class="text-sm text-muted mt-1">{{ number_format($order->total_amount, 0, ',', '.') }} ₫</div>
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    <p>Bạn chưa có đơn nào.</p>
                    <a href="{{ route('panel.orders.create') }}" class="btn btn-primary mt-2">Đặt đơn đầu tiên</a>
                </div>
            @endforelse
        </div>

        <div class="card">
            <h3 class="card-title">Thông báo mới nhất</h3>
            @forelse ($notifications as $n)
                <div style="padding:12px 0;border-bottom:1px solid #EDF2FA;">
                    <div style="font-weight:600;">{{ $n->data['title'] ?? 'Thông báo' }}</div>
                    <div class="text-sm text-muted">{{ $n->data['message'] ?? '' }}</div>
                    <div class="text-sm text-muted mt-1">{{ $n->created_at->diffForHumans() }}</div>
                </div>
            @empty
                <p class="text-muted">Chưa có thông báo nào.</p>
            @endforelse
        </div>
    </div>

    <div class="card mt-3 dot-pattern" style="background-color:#F5F8FF;">
        <div class="flex-between flex-wrap">
            <div>
                <h3 class="card-title mb-1">Cần nạp thêm tiền?</h3>
                <p class="text-muted mb-0">Nạp tiền tự động qua chuyển khoản, cộng ví trong vài giây.</p>
            </div>
            <a href="{{ route('panel.deposit.create') }}" class="btn btn-primary">Nạp tiền ngay</a>
        </div>
    </div>
@endsection
