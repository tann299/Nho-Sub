@extends('layouts.panel')
@section('title', 'Đơn hàng của tôi')
@section('content')
    <div class="page-hero">
        <div class="page-hero-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2"/></svg>
        </div>
        <div class="page-hero-body">
            <h2 class="page-hero-title">Đơn hàng của tôi</h2>
            <p class="page-hero-desc">Trạng thái tự cập nhật mỗi 5 phút. Đơn hủy/partial được hoàn tiền tự động.</p>
        </div>
        <a href="{{ route('panel.orders.create') }}" class="btn btn-primary page-hero-action">Đặt đơn mới</a>
    </div>

    <form method="GET" class="card mb-3">
        <div class="grid grid-3" style="align-items:end;">
            <div class="form-group mb-0">
                <label class="form-label" for="q">Mã đơn hoặc link</label>
                <input class="form-input" id="q" name="q" value="{{ $q }}" placeholder="Ví dụ: 1024">
            </div>
            <div class="form-group mb-0">
                <label class="form-label" for="status">Trạng thái</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tất cả</option>
                    <option value="pending" @selected($status === 'pending')>Chờ xử lý</option>
                    <option value="processing" @selected($status === 'processing')>Đang chạy</option>
                    <option value="completed" @selected($status === 'completed')>Hoàn thành</option>
                    <option value="partial" @selected($status === 'partial')>Hoàn thành một phần</option>
                    <option value="canceled" @selected($status === 'canceled')>Đã hủy</option>
                    <option value="error" @selected($status === 'error')>Lỗi</option>
                </select>
            </div>
            <div class="form-group mb-0">
                <button class="btn btn-primary btn-block" type="submit">Lọc</button>
            </div>
        </div>
    </form>

    <div class="card" style="padding:0;overflow:hidden;">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Mã đơn</th>
                        <th>Dịch vụ</th>
                        <th class="num">Số lượng</th>
                        <th class="num">Đã chạy</th>
                        <th class="num">Còn lại</th>
                        <th class="num">Thành tiền</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td><strong>#{{ $order->id }}</strong></td>
                        <td class="truncate" title="{{ $order->service->name ?? '' }}">{{ $order->service->name ?? '—' }}</td>
                        <td class="num">{{ number_format($order->quantity, 0, ',', '.') }}</td>
                        <td class="num">{{ number_format(max(0, $order->quantity - ($order->remains ?? $order->quantity)), 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($order->remains ?? 0, 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($order->total_amount, 0, ',', '.') }} ₫</td>
                        <td>@include('partials.order-status', ['status' => $order->status])</td>
                        <td class="text-sm text-muted nowrap">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                        <td><a class="btn btn-ghost btn-sm" href="{{ route('panel.orders.show', $order) }}">Chi tiết</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" style="padding:0;border:none;">
                        <div class="empty-block" style="border:none;border-radius:0;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/></svg>
                            <div class="empty-title">Chưa có đơn nào</div>
                            <p>Đơn đầu tiên chỉ mất 30 giây: chọn dịch vụ, dán link, xác nhận.</p>
                            <a href="{{ route('panel.orders.create') }}" class="btn btn-primary">Đặt đơn đầu tiên</a>
                        </div>
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $orders->links() }}</div>
@endsection
