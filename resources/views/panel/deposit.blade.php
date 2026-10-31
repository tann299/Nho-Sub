@extends('layouts.panel')
@section('title', 'Nạp tiền')
@section('content')
    {{-- Khối neo trang: tối, nổi bật nhất --}}
    <div class="page-hero page-hero-deep dot-pattern">
        <div class="page-hero-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 3v12m0 0l4-4m-4 4l-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
        </div>
        <div class="page-hero-body">
            <h2 class="page-hero-title">Nạp tiền vào ví</h2>
            <p class="page-hero-desc">Chuyển khoản đúng nội dung — tiền vào ví tự động trong vài giây.</p>
        </div>
        <a href="{{ route('panel.wallet.index') }}" class="btn btn-primary page-hero-action">Lịch sử giao dịch</a>
    </div>

    <div class="grid grid-2">
        <div class="card">
            <span class="card-label">Bước 1 — Chuyển khoản</span>

            <div class="copy-row">
                <span class="copy-label">Ngân hàng</span>
                <span class="copy-value">{{ $bankName ?: 'Chưa cấu hình' }}</span>
            </div>
            <div class="copy-row">
                <span class="copy-label">Số tài khoản</span>
                <span class="copy-value mono">
                    {{ $bankAccount ?: '—' }}
                    @if ($bankAccount)
                        <button class="btn btn-ghost btn-sm" type="button" style="margin-left:6px;"
                                data-copy="{{ $bankAccount }}" data-copy-message="Đã sao chép số tài khoản">Sao chép</button>
                    @endif
                </span>
            </div>
            <div class="copy-row">
                <span class="copy-label">Chủ tài khoản</span>
                <span class="copy-value">{{ $bankOwner ?: '—' }}</span>
            </div>
            <div class="copy-row" style="border-color:var(--primary);background:var(--surface-tint);">
                <span class="copy-label">Nội dung chuyển khoản</span>
                <span class="copy-value mono" style="color:var(--primary-dark);">{{ $transferContent }}</span>
            </div>
            <button class="btn btn-primary btn-block" type="button"
                    data-copy="{{ $transferContent }}"
                    data-copy-message="Đã sao chép nội dung chuyển khoản">
                Sao chép nội dung chuyển khoản
            </button>

            @if ($bankBin && $bankAccount)
                <div class="divider"></div>
                <span class="card-label">Hoặc quét QR</span>
                <div class="text-center">
                    <span class="qr-frame">
                        <img src="https://img.vietqr.io/image/{{ $bankBin }}-{{ $bankAccount }}-compact2.png?addInfo={{ urlencode($transferContent) }}&accountName={{ urlencode($bankOwner) }}"
                             alt="Mã QR chuyển khoản" style="max-width:220px;display:block;"
                             loading="lazy">
                    </span>
                </div>
            @endif
        </div>

        <div>
            <div class="card mb-2">
                <span class="card-label">Bước 2 — Chờ tiền vào ví</span>

                <div class="step-row">
                    <span class="step-num">1</span>
                    <span class="step-text"><strong>Chuyển đúng nội dung</strong> — sai nội dung tiền không tự cộng được.</span>
                </div>
                <div class="step-row">
                    <span class="step-num">2</span>
                    <span class="step-text"><strong>Vài giây là vào ví</strong> — hệ thống đối chiếu tự động 24/7.</span>
                </div>
                <div class="step-row">
                    <span class="step-num">3</span>
                    <span class="step-text"><strong>Quá 15 phút chưa thấy?</strong> Gửi yêu cầu duyệt tay bên dưới.</span>
                </div>

                <div class="note-block mt-1" style="margin-bottom:0;">
                    Tối thiểu <strong>{{ number_format($minDeposit, 0, ',', '.') }} ₫</strong> mỗi giao dịch.
                    Tiền đã nạp dùng để mua dịch vụ, không rút lại (xem <a href="{{ route('content.terms') }}">Điều khoản</a>).
                </div>
            </div>

            <div class="card">
                <span class="card-label">Bước 3 — Gửi yêu cầu duyệt tay (dự phòng)</span>
                <form method="POST" action="{{ route('panel.deposit.store') }}" data-guard>
                    @csrf
                    <div class="form-group">
                        <label class="form-label" for="amount">Số tiền đã chuyển (₫)</label>
                        <input class="form-input" id="amount" name="amount" type="number" min="{{ $minDeposit }}"
                               value="{{ old('amount') }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="note">Mã giao dịch ngân hàng (nếu có)</label>
                        <input class="form-input" id="note" name="note" value="{{ old('note') }}">
                    </div>
                    <button class="btn btn-outline btn-block" type="submit">Gửi yêu cầu</button>
                </form>
            </div>
        </div>
    </div>

    <div class="card mt-2" style="padding:0;overflow:hidden;">
        <div class="flex-between" style="padding:12px 16px;border-bottom:1px solid var(--primary-light);">
            <span class="card-label" style="margin:0;">Lịch sử yêu cầu nạp</span>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Số tiền</th><th>Phương thức</th><th>Trạng thái</th><th>Thời gian</th></tr>
                </thead>
                <tbody>
                @forelse ($requests as $r)
                    <tr>
                        <td class="num"><strong>{{ number_format($r->amount, 0, ',', '.') }} ₫</strong></td>
                        <td>{{ $r->method === 'payos' ? 'PayOS' : 'Chuyển khoản' }}</td>
                        <td>@include('partials.order-status', ['status' => $r->status])</td>
                        <td class="text-sm text-muted">{{ $r->created_at?->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="padding:0;border:none;">
                            <div class="empty-block" style="border:none;border-radius:0;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M12 3v12m0 0l4-4m-4 4l-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                                <div class="empty-title">Chưa có yêu cầu nạp nào</div>
                                <p>Lịch sử các lần nạp sẽ hiện ở đây sau giao dịch đầu tiên.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
