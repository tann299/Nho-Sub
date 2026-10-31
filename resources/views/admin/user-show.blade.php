@extends('layouts.panel')
@section('title', 'Người dùng ' . $user->username)
@section('content')
    <div class="grid grid-2">
        <div class="card">
            <h2 class="card-title">{{ $user->username }}</h2>
            <table class="table">
                <tbody>
                    <tr><th>Họ tên</th><td>{{ $user->full_name ?: '—' }}</td></tr>
                    <tr><th>Email</th><td>{{ $user->email }}</td></tr>
                    <tr><th>SĐT</th><td>{{ $user->phone ?: '—' }}</td></tr>
                    <tr><th>Số dư</th><td><strong>{{ number_format($user->balance, 0, ',', '.') }} ₫</strong></td></tr>
                    <tr><th>Tổng đã nạp</th><td>{{ number_format($totalDeposit, 0, ',', '.') }} ₫</td></tr>
                    <tr><th>Số đơn</th><td>{{ $orderCount }}</td></tr>
                    <tr><th>Trạng thái</th><td>{{ $user->status === 'active' ? 'Hoạt động' : 'Đã khoá' }}</td></tr>
                    <tr><th>Đăng nhập gần nhất</th><td>{{ $user->last_login_at?->format('d/m/Y H:i') ?? '—' }}</td></tr>
                </tbody>
            </table>

            <form method="POST" action="{{ route('admin.users.adjust', $user) }}" class="mt-2" data-guard>
                @csrf
                <div class="form-group">
                    <label class="form-label" for="amount">Cộng / trừ tiền (₫)</label>
                    <input class="form-input" id="amount" name="amount" type="number" required
                           placeholder="Số dương để cộng, số âm để trừ">
                </div>
                <div class="form-group">
                    <label class="form-label" for="reason">Lý do (bắt buộc)</label>
                    <input class="form-input" id="reason" name="reason" required maxlength="200">
                </div>
                <button class="btn btn-primary" type="submit">Áp dụng</button>
            </form>
        </div>

        <div class="card">
            <h3 class="card-title">Giao dịch gần đây</h3>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Thời gian</th><th>Loại</th><th class="num">Số tiền</th></tr></thead>
                    <tbody>
                    @forelse ($transactions as $tx)
                        <tr>
                            <td class="text-sm text-muted">{{ $tx->created_at?->format('d/m H:i') }}</td>
                            <td class="text-sm">{{ $tx->type }}</td>
                            <td class="num {{ $tx->amount >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ $tx->amount >= 0 ? '+' : '' }}{{ number_format($tx->amount, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted">Chưa có giao dịch.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <a href="{{ route('admin.users.index') }}" class="btn btn-ghost mt-3">← Về danh sách</a>
@endsection
