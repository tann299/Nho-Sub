@extends('layouts.panel')
@section('title', 'Đơn hàng #' . $order->id)
@section('content')
    <div class="grid grid-2">
        <div class="card">
            <div class="flex-between mb-2">
                <h2 class="card-title mb-0">Đơn #{{ $order->id }}</h2>
                @include('partials.order-status', ['status' => $order->status])
            </div>

            <div class="table-wrap">
                <table class="table">
                    <tbody>
                        <tr><th style="width:45%;">Dịch vụ</th><td>{{ $order->service->name ?? '—' }}</td></tr>
                        <tr><th>Link</th><td><a href="{{ $order->target_link }}" target="_blank" rel="noopener noreferrer" class="truncate link-underline">{{ $order->target_link }}</a></td></tr>
                        <tr><th>Số lượng đặt</th><td>{{ number_format($order->quantity, 0, ',', '.') }}</td></tr>
                        <tr><th>Đã chạy</th><td>{{ number_format(max(0, $order->quantity - ($order->remains ?? $order->quantity)), 0, ',', '.') }}</td></tr>
                        <tr><th>Còn lại</th><td>{{ number_format($order->remains ?? 0, 0, ',', '.') }}</td></tr>
                        <tr><th>Giá / 1.000</th><td>{{ number_format($order->unit_price, 0, ',', '.') }} ₫</td></tr>
                        <tr><th>Thành tiền</th><td><strong>{{ number_format($order->total_amount, 0, ',', '.') }} ₫</strong></td></tr>
                        <tr><th>Đã hoàn</th><td class="text-success">{{ number_format($order->refunded_amount, 0, ',', '.') }} ₫</td></tr>
                        <tr><th>Ngày tạo</th><td>{{ $order->created_at->format('d/m/Y H:i:s') }}</td></tr>
                        <tr><th>Cập nhật gần nhất</th><td>{{ ($order->synced_at ?? $order->updated_at)?->format('d/m/Y H:i:s') }}</td></tr>
                    </tbody>
                </table>
            </div>

            @unless ($order->isFinished())
                <form method="POST" action="{{ route('panel.orders.refresh', $order) }}" class="mt-2">
                    @csrf
                    <button class="btn btn-outline" type="submit">Cập nhật trạng thái</button>
                </form>
            @endunless
        </div>

        <div class="card">
            <h3 class="card-title">Lịch sử hoàn tiền</h3>
            @forelse ($order->refunds as $refund)
                <div class="flex-between" style="padding:10px 0;border-bottom:1px solid #EDF2FA;">
                    <div>
                        <strong>Đợt {{ $refund->seq }}</strong>
                        <div class="text-sm text-muted">
                            @switch($refund->reason)
                                @case('auto_partial') Hoàn phần chưa chạy (một phần) @break
                                @case('auto_canceled') Hoàn phần chưa chạy (đơn hủy) @break
                                @case('provider_error') Hoàn do lỗi nhà cung cấp @break
                                @default Hoàn tiền thủ công
                            @endswitch
                        </div>
                    </div>
                    <strong class="text-success">+{{ number_format($refund->amount, 0, ',', '.') }} ₫</strong>
                </div>
            @empty
                <p class="text-muted">Chưa có khoản hoàn nào.</p>
            @endforelse
        </div>
    </div>

    <a href="{{ route('panel.orders.index') }}" class="btn btn-ghost mt-3">← Về danh sách đơn</a>
@endsection
