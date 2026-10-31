@extends('layouts.panel')
@section('title', 'Tổng quan')
@section('content')
    <div class="grid grid-4 mb-3">
        <div class="stat">
            <div class="stat-label">Số dư ví</div>
            <div class="stat-value primary">{{ number_format($stats['balance'], 0, ',', '.') }} ₫</div>
        </div>
        <div class="stat">
            <div class="stat-label">Tổng số đơn</div>
            <div class="stat-value">{{ number_format($stats['total_orders'], 0, ',', '.') }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Đơn đang chạy</div>
            <div class="stat-value warning">{{ number_format($stats['running_orders'], 0, ',', '.') }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Thông báo mới</div>
            <div class="stat-value">{{ $notifications->whereNull('read_at')->count() }}</div>
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
