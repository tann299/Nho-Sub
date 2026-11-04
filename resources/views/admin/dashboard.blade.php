@extends('layouts.panel')
@section('title', 'Tổng quan quản trị')
@section('content')
    @if ($lowBalance)
        <div class="alert alert-warning">
            Số dư TopSubRe thấp ({{ $providerBalance }} USD). Vui lòng nạp thêm để tránh đơn bị kẹt.
        </div>
    @endif

    @if ($stuckCount > 0)
        <div class="alert alert-error">
            Có {{ $stuckCount }} đơn cần xử lý tay.
            <a href="{{ route('admin.orders.index', ['status' => 'stuck_review']) }}">Xem ngay</a>
        </div>
    @endif

    <div class="grid grid-4 mb-3">
        <div class="stat">
            <div class="stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
            <div class="stat-body">
                <p class="stat-label">Doanh thu hôm nay</p>
                <div class="stat-value primary">{{ number_format($revenue, 0, ',', '.') }} ₫</div>
            </div>
        </div>
        <div class="stat">
            <div class="stat-icon warning">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7l-8-4-8 4 8 4 8-4zM4 12l8 4 8-4M4 17l8 4 8-4"/></svg>
            </div>
            <div class="stat-body">
                <p class="stat-label">Chi phí gốc</p>
                <div class="stat-value">{{ number_format($cost, 0, ',', '.') }} ₫</div>
            </div>
        </div>
        <div class="stat">
            <div class="stat-icon {{ $profit >= 0 ? 'success' : 'danger' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 6l-9.5 9.5-5-5L1 18"/><path d="M17 6h6v6"/></svg>
            </div>
            <div class="stat-body">
                <p class="stat-label">Lợi nhuận gộp</p>
                <div class="stat-value {{ $profit >= 0 ? 'success' : 'danger' }}">{{ number_format($profit, 0, ',', '.') }} ₫</div>
            </div>
        </div>
        <div class="stat">
            <div class="stat-icon {{ $lowBalance ? 'danger' : 'success' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
            </div>
            <div class="stat-body">
                <p class="stat-label">Số dư TopSubRe</p>
                <div class="stat-value {{ $lowBalance ? 'warning' : '' }}">
                    {{ $providerBalance !== null ? number_format((float) $providerBalance, 2) . ' USD' : 'Không lấy được' }}
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-3 mb-3">
        <div class="stat">
            <div class="stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/></svg>
            </div>
            <div class="stat-body"><p class="stat-label">Tổng đơn</p><div class="stat-value">{{ number_format($totalOrders) }}</div></div>
        </div>
        <div class="stat">
            <div class="stat-icon success">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/></svg>
            </div>
            <div class="stat-body"><p class="stat-label">Khách hàng</p><div class="stat-value">{{ number_format($userCount) }}</div></div>
        </div>
        <div class="stat">
            <div class="stat-icon {{ $pendingDeposits > 0 ? 'warning' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 3v12m0 0l4-4m-4 4l-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
            </div>
            <div class="stat-body"><p class="stat-label">Nạp chờ duyệt</p><div class="stat-value {{ $pendingDeposits > 0 ? 'warning' : '' }}">{{ $pendingDeposits }}</div></div>
        </div>
    </div>

    {{-- Việc cần xử lý — chỉ hiện khi có, bấm thẳng vào đúng nơi xử lý --}}
    @php
        $todo = [
            ['Nạp tiền chờ duyệt', $pendingDeposits, route('admin.deposits.index'), $pendingDeposits > 0],
            ['Đơn kẹt cần xử lý tay', $stuckCount, route('admin.orders.index', ['status' => 'stuck_review']), $stuckCount > 0],
        ];
    @endphp
    <div class="card">
        <h3 class="card-title">Việc cần xử lý</h3>
        <div class="admin-todo-row">
            @foreach ($todo as [$label, $count, $url, $hot])
                <a href="{{ $url }}" class="admin-todo {{ $hot ? 'admin-todo-hot' : '' }}">
                    <span class="admin-todo-count">{{ $count }}</span>
                    <span class="admin-todo-label">{{ $label }}</span>
                </a>
            @endforeach
        </div>
        <p class="text-sm text-muted mt-2 mb-0" style="margin-top:10px;">
            Lợi nhuận gộp chưa trừ chi phí hosting, tên miền và chi phí vận hành khác.
        </p>
    </div>

    <div class="card mt-3" style="padding:0;overflow:hidden;">
        <div class="flex-between" style="padding:12px 16px;border-bottom:1px solid var(--primary-light);">
            <span class="card-label" style="margin:0;">Đơn theo trạng thái</span>
            <a href="{{ route('admin.orders.index') }}" class="text-sm link-underline">Tất cả đơn</a>
        </div>
        <div class="admin-status-grid">
            @forelse (($byStatus ?? []) as $status => $count)
                <a class="admin-status-cell" href="{{ route('admin.orders.index', ['status' => $status]) }}">
                    @include('partials.order-status', ['status' => $status])
                    <span class="admin-status-num">{{ number_format($count) }}</span>
                </a>
            @empty
                <p class="text-sm text-muted" style="padding:10px 16px;">Chưa có đơn nào — lưới trạng thái sẽ hiện khi có đơn đầu tiên.</p>
            @endforelse
        </div>
    </div>
@endsection
