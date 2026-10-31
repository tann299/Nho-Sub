@extends('layouts.panel')
@section('title', 'Lịch sử giao dịch')
@section('content')
    <div class="page-hero">
        <div class="page-hero-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 7a2 2 0 0 1 2-2h13a1 1 0 0 1 1 1v3"/><path d="M3 7v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H5"/><circle cx="17" cy="14" r="1"/></svg>
        </div>
        <div class="page-hero-body">
            <h2 class="page-hero-title">Lịch sử giao dịch</h2>
            <p class="page-hero-desc">Mọi dòng cộng/trừ đều có số dư sau giao dịch — đối chiếu được từng đồng.</p>
        </div>
        <div class="page-hero-action stat stat-stack" style="padding:8px 16px;text-align:right;">
            <p class="stat-label">Số dư hiện tại</p>
            <div class="stat-value primary" style="font-size:17px;">{{ number_format($user->balance, 0, ',', '.') }} ₫</div>
        </div>
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
                    <tr><td colspan="5" style="padding:0;border:none;">
                        <div class="empty-block" style="border:none;border-radius:0;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M3 7a2 2 0 0 1 2-2h13a1 1 0 0 1 1 1v3"/><path d="M3 7v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H5"/><circle cx="17" cy="14" r="1"/></svg>
                            <div class="empty-title">Chưa có giao dịch nào</div>
                            <p>Nạp tiền đầu tiên để bắt đầu đặt đơn.</p>
                            <a href="{{ route('panel.deposit.create') }}" class="btn btn-primary">Nạp tiền ngay</a>
                        </div>
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $transactions->links() }}</div>
@endsection
