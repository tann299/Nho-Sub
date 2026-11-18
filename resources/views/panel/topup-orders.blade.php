@extends('layouts.panel')
@section('title', 'Đơn nạp xu')
@section('content')
    <div class="page-hero">
        <div class="page-hero-body"><h2 class="page-hero-title">Đơn nạp xu</h2><p class="page-hero-desc">Đơn nạp TikTok tách riêng với đơn tăng tương tác. Các khoản trừ và hoàn đều ghi trong ví NhoSub.</p></div>
        <a class="btn btn-primary" href="{{ route('panel.topup.create') }}">Nạp xu TikTok</a>
    </div>
    <div class="card table-wrap">
        <table class="table">
            <thead><tr><th>Mã đơn</th><th>TikTok</th><th class="num">Xu</th><th class="num">Đã trả</th><th class="num">Đã hoàn</th><th>Trạng thái</th><th>Ngày</th><th>Thao tác</th></tr></thead>
            <tbody>
            @forelse ($orders as $order)
                <tr>
                    <td>NX-{{ $order->id }}<div class="text-sm text-muted">{{ $order->napxu_order_ref }}</div></td>
                    <td>{{ $order->account?->username ?? 'Đã gỡ liên kết' }}</td>
                    <td class="num">{{ number_format($order->coins, 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($order->sell_price, 0, ',', '.') }} ₫</td>
                    <td class="num">{{ number_format($order->refunded_amount, 0, ',', '.') }} ₫</td>
                    <td>@include('partials.topup-status', ['status' => $order->status])</td>
                    <td class="text-sm">{{ $order->created_at?->format('d/m/Y H:i') }}</td>
                    <td>
                        @if (! $order->isFinal() && $order->napxu_order_id)
                            <form method="POST" action="{{ route('panel.topup.refresh', $order->id) }}">@csrf<button type="submit" class="btn btn-outline btn-sm">Cập nhật</button></form>
                        @endif
                        @if ($order->status === 'pending' && $order->napxu_order_id)
                            <form method="POST" class="mt-1" action="{{ route('panel.topup.cancel', $order->id) }}" onsubmit="return confirm('Gửi yêu cầu hủy đơn này tới Napxu? Chỉ hoàn ví sau khi Napxu xác nhận.');">@csrf<button type="submit" class="btn btn-ghost btn-sm">Yêu cầu hủy</button></form>
                        @endif
                        @if ($order->status === 'stuck_review')<span class="text-sm text-muted">Không đặt lại; liên hệ hỗ trợ.</span>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8">Chưa có đơn nạp xu. Liên kết TikTok và chọn gói để bắt đầu.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $orders->links() }}</div>
@endsection
