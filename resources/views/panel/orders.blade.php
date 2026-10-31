@extends('layouts.panel')
@section('title', 'Đơn hàng của tôi')
@section('content')
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
                    <tr><td colspan="9" class="text-center text-muted" style="padding:40px;">Chưa có đơn nào.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $orders->links() }}</div>
@endsection
