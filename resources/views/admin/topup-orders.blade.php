@extends('layouts.panel')
@section('title', 'Đơn nạp xu TikTok')
@section('content')
    <div class="page-hero">
        <div class="page-hero-body">
            <h2 class="page-hero-title">Đơn nạp xu TikTok</h2>
            <p class="page-hero-desc">Đơn gửi kẹt (chờ đối soát) cần admin gắn biên nhận Napxu rồi mới hoàn tiền — không hoàn trùng nhờ khóa giao dịch.</p>
        </div>
    </div>

    <form method="GET" class="card mb-3 flex gap-1 flex-wrap" style="align-items:end;">
        <div class="form-group mb-0">
            <label class="form-label" for="status">Trạng thái</label>
            <select class="form-select" id="status" name="status" onchange="this.form.submit()">
                <option value="">Tất cả</option>
                @foreach (['pending','charged','processing','success','failed','refunded','canceled','stuck_review'] as $s)
                    <option value="{{ $s }}" @selected($status === $s)>{{ $s }}</option>
                @endforeach
            </select>
        </div>
    </form>

    <div class="card" style="padding:0;overflow:hidden;">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Mã</th><th>Khách</th><th>TikTok</th><th class="num">Xu</th><th class="num">Thu</th><th class="num">Chi</th><th class="num">Đã hoàn</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                <tbody>
                @forelse ($orders as $o)
                    <tr>
                        <td><strong>NX-{{ $o->id }}</strong><div class="text-sm text-muted mono">{{ $o->napxu_order_ref ?? '—' }}</div></td>
                        <td class="text-sm">{{ $o->user->username ?? '—' }}</td>
                        <td class="text-sm">{{ $o->account?->username ?? 'Đã gỡ' }}</td>
                        <td class="num">{{ number_format($o->coins, 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($o->sell_price, 0, ',', '.') }}</td>
                        <td class="num text-muted">{{ number_format($o->cost_price, 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($o->refunded_amount, 0, ',', '.') }}</td>
                        <td>@include('partials.topup-status', ['status' => $o->status])</td>
                        <td>
                            <div class="flex gap-1 flex-wrap">
                                @if (in_array($o->status, ['pending', 'processing']))
                                    <form method="POST" action="{{ route('admin.topupOrders.refresh', $o) }}">@csrf<button class="btn btn-outline btn-sm" type="submit">Đồng bộ</button></form>
                                @endif
                                @if ($o->status === 'stuck_review')
                                    <form method="POST" action="{{ route('admin.topupOrders.reconcile', $o) }}" class="flex gap-1" style="align-items:end;">
                                        @csrf
                                        <input class="form-input" name="napxu_order_id" type="number" min="1" placeholder="Mã đơn Napxu" style="width:130px;" required>
                                        <input type="hidden" name="confirmed" value="1">
                                        <button class="btn btn-outline btn-sm" type="submit" onclick="return confirm('Gắn biên nhận Napxu cho đơn này? Hệ thống sẽ đối chiếu tài khoản + số xu rồi cập nhật, không gửi lại đơn.');">Đối soát</button>
                                    </form>
                                @endif
                                @if (in_array($o->status, ['stuck_review', 'failed', 'refunded', 'canceled']) && $o->refunded_amount < $o->sell_price)
                                    <form method="POST" action="{{ route('admin.topupOrders.refund', $o) }}">
                                        @csrf
                                        <input type="hidden" name="confirmed" value="1">
                                        <button class="btn btn-danger btn-sm" type="submit" onclick="return confirm('Hoàn {{ number_format($o->sell_price - $o->refunded_amount, 0, chr(44), chr(46)) }} ₫ về ví khách? Khóa giao dịch chặn hoàn trùng.');">Hoàn {{ number_format($o->sell_price - $o->refunded_amount, 0, ',', '.') }} ₫</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9">Chưa có đơn nạp xu nào.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $orders->links() }}</div>
@endsection
