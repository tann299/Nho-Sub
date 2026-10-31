@extends('layouts.panel')
@section('title', 'Đơn #' . $order->id)
@section('content')
    <div class="grid grid-2">
        <div class="card">
            <div class="flex-between mb-2">
                <h2 class="card-title mb-0">Đơn #{{ $order->id }}</h2>
                @include('partials.order-status', ['status' => $order->status])
            </div>

            <table class="table">
                <tbody>
                    <tr><th>Khách</th><td>{{ $order->user->username ?? '—' }}</td></tr>
                    <tr><th>Dịch vụ</th><td>{{ $order->service->name ?? '—' }} (ID NCC: {{ $order->service->provider_service_id ?? '—' }})</td></tr>
                    <tr><th>Mã NCC</th><td class="mono">{{ $order->provider_order_id ?: '—' }}</td></tr>
                    <tr><th>Link</th><td><a href="{{ $order->target_link }}" target="_blank" rel="noopener noreferrer" class="truncate link-underline">{{ $order->target_link }}</a></td></tr>
                    <tr><th>Số lượng</th><td>{{ number_format($order->quantity, 0, ',', '.') }}</td></tr>
                    <tr><th>Start count</th><td>{{ $order->start_count ?? '—' }}</td></tr>
                    <tr><th>Remains</th><td>{{ $order->remains ?? '—' }}</td></tr>
                    <tr><th>Đơn giá /1.000</th><td>{{ number_format($order->unit_price, 0, ',', '.') }} ₫</td></tr>
                    <tr><th>Khách trả</th><td>{{ number_format($order->total_amount, 0, ',', '.') }} ₫</td></tr>
                    <tr><th>Chi phí gốc</th><td>{{ number_format($order->cost_amount, 0, ',', '.') }} ₫</td></tr>
                    <tr><th>Đã hoàn</th><td>{{ number_format($order->refunded_amount, 0, ',', '.') }} ₫ ({{ $order->refund_count }} đợt)</td></tr>
                    <tr><th>Lỗi</th><td class="text-danger">{{ $order->error_message ?: '—' }}</td></tr>
                </tbody>
            </table>

            @if ($order->status === \App\Models\Order::STATUS_STUCK_REVIEW)
                <div class="alert alert-warning mt-2">
                    Đơn bị kẹt sau khi đã trừ tiền. Kiểm tra nhật ký API bên phải trước khi xử lý.
                </div>
                <div class="grid grid-2">
                    <form method="POST" action="{{ route('admin.orders.resolveStuck', $order) }}" data-guard>
                        @csrf
                        <input type="hidden" name="action" value="attach">
                        <div class="form-group">
                            <label class="form-label">Đã gửi NCC — nhập mã đơn</label>
                            <input class="form-input" name="provider_order_id" placeholder="Mã đơn từ TopSubRe">
                        </div>
                        <button class="btn btn-outline btn-block" type="submit">Gắn mã & tiếp tục</button>
                    </form>

                    <form method="POST" action="{{ route('admin.orders.resolveStuck', $order) }}" data-guard
                          data-confirm="Hoàn tiền toàn bộ và đóng đơn?">
                        @csrf
                        <input type="hidden" name="action" value="refund">
                        <div class="form-group">
                            <label class="form-label">Chưa gửi — lý do</label>
                            <input class="form-input" name="reason" value="Xác nhận chưa gửi nhà cung cấp">
                        </div>
                        <button class="btn btn-danger btn-block" type="submit">Hoàn tiền & đóng đơn</button>
                    </form>
                </div>
            @endif

            @if (! $order->isFinished() && $order->refunded_amount < $order->total_amount)
                <form method="POST" action="{{ route('admin.orders.refund', $order) }}" class="mt-2" data-guard
                      data-confirm="Hoàn phần chưa chạy cho khách?">
                    @csrf
                    <div class="form-group">
                        <label class="form-label">Hoàn tiền thủ công — lý do</label>
                        <input class="form-input" name="reason" required>
                    </div>
                    <button class="btn btn-outline" type="submit">Hoàn phần chưa chạy</button>
                </form>
            @endif
        </div>

        <div class="card">
            <h3 class="card-title">Nhật ký API</h3>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Thời gian</th><th>Hành động</th><th>Kết quả</th></tr></thead>
                    <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="text-sm text-muted nowrap">{{ $log->created_at?->format('d/m H:i:s') }}</td>
                            <td class="text-sm">{{ $log->action }}</td>
                            <td class="text-sm">
                                <span class="badge {{ $log->success ? 'badge-completed' : 'badge-error' }}">
                                    {{ $log->success ? 'OK' : 'Lỗi' }}
                                </span>
                                <div class="text-sm text-muted">{{ $log->response_summary }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted">Chưa có nhật ký.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
