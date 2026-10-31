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
            <div class="stat-label">Doanh thu hôm nay</div>
            <div class="stat-value primary">{{ number_format($revenue, 0, ',', '.') }} ₫</div>
        </div>
        <div class="stat">
            <div class="stat-label">Chi phí gốc</div>
            <div class="stat-value">{{ number_format($cost, 0, ',', '.') }} ₫</div>
        </div>
        <div class="stat">
            <div class="stat-label">Lợi nhuận gộp</div>
            <div class="stat-value {{ $profit >= 0 ? 'success' : 'danger' }}">{{ number_format($profit, 0, ',', '.') }} ₫</div>
        </div>
        <div class="stat">
            <div class="stat-label">Số dư TopSubRe</div>
            <div class="stat-value {{ $lowBalance ? 'warning' : '' }}">
                {{ $providerBalance !== null ? number_format((float) $providerBalance, 2) . ' USD' : 'Không lấy được' }}
            </div>
        </div>
    </div>

    <div class="grid grid-3 mb-3">
        <div class="stat"><div class="stat-label">Tổng đơn</div><div class="stat-value">{{ number_format($totalOrders) }}</div></div>
        <div class="stat"><div class="stat-label">Khách hàng</div><div class="stat-value">{{ number_format($userCount) }}</div></div>
        <div class="stat"><div class="stat-label">Nạp chờ duyệt</div><div class="stat-value {{ $pendingDeposits > 0 ? 'warning' : '' }}">{{ $pendingDeposits }}</div></div>
    </div>

    <div class="card">
        <h3 class="card-title">Đơn theo trạng thái</h3>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Trạng thái</th><th class="num">Số đơn</th></tr></thead>
                <tbody>
                @foreach ($byStatus as $status => $count)
                    <tr>
                        <td>@include('partials.order-status', ['status' => $status])</td>
                        <td class="num">{{ number_format($count) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-sm text-muted mt-2 mb-0">
            Lợi nhuận gộp chưa trừ chi phí hosting, tên miền và chi phí vận hành khác.
        </p>
    </div>
@endsection
