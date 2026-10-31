@extends('layouts.panel')
@section('title', 'Nạp tiền')
@section('content')
    <div class="grid grid-2">
        <div class="card">
            <h2 class="card-title">Chuyển khoản ngân hàng</h2>
            <p class="text-muted text-sm">
                Chuyển đúng <strong>nội dung</strong> bên dưới, hệ thống sẽ tự cộng tiền trong vài giây.
                Tối thiểu {{ number_format($minDeposit, 0, ',', '.') }} ₫.
            </p>

            <div class="table-wrap">
                <table class="table">
                    <tbody>
                        <tr><th style="width:42%;">Ngân hàng</th><td>{{ $bankName ?: 'Chưa cấu hình' }}</td></tr>
                        <tr><th>Số tài khoản</th><td><strong class="mono">{{ $bankAccount ?: '—' }}</strong></td></tr>
                        <tr><th>Chủ tài khoản</th><td>{{ $bankOwner ?: '—' }}</td></tr>
                        <tr>
                            <th>Nội dung chuyển khoản</th>
                            <td>
                                <span class="mono" style="font-weight:700;color:var(--primary);">{{ $transferContent }}</span>
                                <button class="btn btn-ghost btn-sm mt-1" type="button"
                                        data-copy="{{ $transferContent }}"
                                        data-copy-message="Đã sao chép nội dung chuyển khoản">
                                    Sao chép
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            @if ($bankBin && $bankAccount)
                <div class="alert alert-info mt-2">
                    Quét mã QR bằng app ngân hàng để điền sẵn thông tin chuyển khoản.
                </div>
                <img src="https://img.vietqr.io/image/{{ $bankBin }}-{{ $bankAccount }}-compact2.png?addInfo={{ urlencode($transferContent) }}&accountName={{ urlencode($bankOwner) }}"
                     alt="Mã QR chuyển khoản" style="max-width:260px;border-radius:12px;border:1px solid var(--primary-light);"
                     loading="lazy">
            @endif
        </div>

        <div>
            <div class="card mb-3">
                <h2 class="card-title">Gửi yêu cầu nạp (duyệt tay)</h2>
                <p class="text-muted text-sm">Dùng khi đã chuyển khoản nhưng chưa thấy tiền vào ví.</p>
                <form method="POST" action="{{ route('panel.deposit.store') }}" data-guard>
                    @csrf
                    <div class="form-group">
                        <label class="form-label" for="amount">Số tiền đã chuyển (₫)</label>
                        <input class="form-input" id="amount" name="amount" type="number" min="{{ $minDeposit }}"
                               value="{{ old('amount') }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="note">Ghi chú</label>
                        <input class="form-input" id="note" name="note" value="{{ old('note') }}"
                               placeholder="Mã giao dịch ngân hàng (nếu có)">
                    </div>
                    <button class="btn btn-primary btn-block" type="submit">Gửi yêu cầu</button>
                </form>
            </div>

            <div class="card">
                <h3 class="card-title">Lịch sử yêu cầu nạp</h3>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>Số tiền</th><th>Phương thức</th><th>Trạng thái</th><th>Thời gian</th></tr>
                        </thead>
                        <tbody>
                        @forelse ($requests as $r)
                            <tr>
                                <td class="num">{{ number_format($r->amount, 0, ',', '.') }} ₫</td>
                                <td>{{ $r->method === 'payos' ? 'PayOS' : 'Chuyển khoản' }}</td>
                                <td>@include('partials.order-status', ['status' => $r->status])</td>
                                <td class="text-sm text-muted">{{ $r->created_at?->format('d/m/Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">Chưa có yêu cầu nào.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
