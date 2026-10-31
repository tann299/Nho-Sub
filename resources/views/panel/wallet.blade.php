@extends('layouts.panel')
@section('title', 'Lịch sử giao dịch')
@section('content')
    <div class="stat mb-3" style="max-width:360px;">
        <div class="stat-label">Số dư hiện tại</div>
        <div class="stat-value primary">{{ number_format($user->balance, 0, ',', '.') }} ₫</div>
    </div>

    <form method="GET" class="mb-2 flex gap-1 flex-wrap">
        <select class="form-select" name="type" style="max-width:220px;" onchange="this.form.submit()">
            <option value="">Tất cả giao dịch</option>
            <option value="deposit" @selected($type === 'deposit')>Nạp tiền</option>
            <option value="order_charge" @selected($type === 'order_charge')>Thanh toán đơn</option>
            <option value="refund" @selected($type === 'refund')>Hoàn tiền</option>
            <option value="adjust" @selected($type === 'adjust')>Điều chỉnh</option>
        </select>
    </form>

    <div class="card" style="padding:0;overflow:hidden;">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Thời gian</th><th>Loại</th><th>Nội dung</th><th class="num">Số tiền</th><th class="num">Số dư sau</th></tr>
                </thead>
                <tbody>
                @forelse ($transactions as $tx)
                    <tr>
                        <td class="text-sm text-muted nowrap">{{ $tx->created_at?->format('d/m/Y H:i') }}</td>
                        <td>
                            @switch($tx->type)
                                @case('deposit') <span class="badge badge-completed">Nạp tiền</span> @break
                                @case('order_charge') <span class="badge badge-processing">Thanh toán</span> @break
                                @case('refund') <span class="badge badge-partial">Hoàn tiền</span> @break
                                @default <span class="badge badge-neutral">Điều chỉnh</span>
                            @endswitch
                        </td>
                        <td class="text-sm">{{ $tx->note }}</td>
                        <td class="num {{ $tx->amount >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $tx->amount >= 0 ? '+' : '' }}{{ number_format($tx->amount, 0, ',', '.') }} ₫
                        </td>
                        <td class="num">{{ number_format($tx->balance_after, 0, ',', '.') }} ₫</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted" style="padding:40px;">Chưa có giao dịch nào.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $transactions->links() }}</div>
@endsection
