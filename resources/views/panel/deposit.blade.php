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
            <p class="page-hero-desc">Nhập số tiền → quét QR → tiền vào ví tự động trong vài giây.</p>
        </div>
        <a href="{{ route('panel.wallet.index') }}" class="btn btn-primary page-hero-action">Lịch sử giao dịch</a>
    </div>

    <div class="grid grid-2">
        {{-- CỘT TRÁI: QR động + thông tin CK --}}
        <div class="card">
            <span class="card-label">Bước 1 — Nhập số tiền & quét QR</span>

            <div class="form-group">
                <label class="form-label" for="qr-amount">Số tiền muốn nạp (₫)</label>
                <input class="form-input" id="qr-amount" type="number" min="{{ $minDeposit }}"
                       value="{{ max($minDeposit, 50000) }}" inputmode="numeric">
                <div class="form-hint">Tối thiểu {{ number_format($minDeposit, 0, ',', '.') }} ₫. QR và nội dung CK tự cập nhật theo số tiền.</div>
            </div>

            @if ($bankBin && $bankAccount)
                <div class="text-center mb-2">
                    <span class="qr-frame">
                        <img id="qr-img" src=""
                             alt="Mã QR chuyển khoản" style="max-width:220px;display:block;"
                             loading="lazy">
                    </span>
                </div>
            @endif

            <div class="copy-row" style="border-color:var(--primary);background:var(--surface-tint);">
                <span class="copy-label">Nội dung chuyển khoản</span>
                <span class="copy-value mono" style="color:var(--primary-dark);" id="transfer-content-dyn">{{ $transferContent }}</span>
            </div>
            <button class="btn btn-primary btn-block" type="button" id="btn-copy-dyn"
                    data-copy-message="Đã sao chép nội dung chuyển khoản">
                Sao chép nội dung chuyển khoản
            </button>

            @if ($pendingRequest)
                <div class="note-block mt-2" style="margin-bottom:0;">
                    Yêu cầu duyệt tay #{{ $pendingRequest->id }} đang chờ với mã xác thực
                    <strong class="mono">{{ $pendingRequest->verification_code }}</strong> —
                    chuyển khoản kèm mã này sẽ được cộng tự động, không cần chờ admin.
                </div>
            @endif

            <div class="divider"></div>
            <span class="card-label">Thông tin tài khoản nhận</span>

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
            <div class="copy-row" style="margin-bottom:0;">
                <span class="copy-label">Chủ tài khoản</span>
                <span class="copy-value">{{ $bankOwner ?: '—' }}</span>
            </div>
        </div>

        {{-- CỘT PHẢI: hướng dẫn + duyệt tay --}}
        <div>
            <div class="card mb-2">
                <span class="card-label">Bước 2 — Chờ tiền vào ví</span>

                <div class="step-row">
                    <span class="step-num">1</span>
                    <span class="step-text"><strong>Nhập số tiền</strong> — QR và nội dung CK tự cập nhật.</span>
                </div>
                <div class="step-row">
                    <span class="step-num">2</span>
                    <span class="step-text"><strong>Quét QR hoặc chuyển thủ công</strong> — giữ nguyên nội dung CK hệ thống tạo.</span>
                </div>
                <div class="step-row">
                    <span class="step-num">3</span>
                    <span class="step-text"><strong>Tiền vào ví trong vài giây</strong> — hệ thống đối chiếu tự động 24/7.</span>
                </div>

                <div class="note-block mt-1" style="margin-bottom:0;">
                    Tối thiểu <strong>{{ number_format($minDeposit, 0, ',', '.') }} ₫</strong> mỗi giao dịch.
                    Tiền đã nạp dùng để mua dịch vụ, không rút lại (xem <a href="{{ route('panel.support.index', ['tab' => 'terms']) }}">Điều khoản</a>).
                </div>
            </div>

            <div class="card">
                <span class="card-label">Dự phòng — Gửi yêu cầu duyệt tay</span>
                <p class="text-sm text-muted">Dùng khi chuyển khoản nhưng quá 15 phút chưa thấy tiền vào ví.</p>
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
                    <tr><th>Số tiền</th><th>Mã xác thực</th><th>Phương thức</th><th>Trạng thái</th><th>Thời gian</th></tr>
                </thead>
                <tbody>
                @forelse ($requests as $r)
                    <tr>
                        <td class="num"><strong>{{ number_format($r->amount, 0, ',', '.') }} ₫</strong></td>
                        <td class="mono">{{ $r->verification_code ?: '—' }}</td>
                        <td>{{ $r->method === 'payos' ? 'PayOS' : 'Chuyển khoản' }}</td>
                        <td>@include('partials.order-status', ['status' => $r->status])</td>
                        <td class="text-sm text-muted">{{ $r->created_at?->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="padding:0;border:none;">
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

    @push('scripts')
    <script>
        (function () {
            var amountInput = document.getElementById('qr-amount');
            var qrImg = document.getElementById('qr-img');
            var contentEl = document.getElementById('transfer-content-dyn');
            var copyBtn = document.getElementById('btn-copy-dyn');
            var base = {{ json_encode($transferContent) }};
            var bin = {{ json_encode($bankBin) }};
            var account = {{ json_encode($bankAccount) }};
            var owner = {{ json_encode($bankOwner) }};

            function fmt(n) { return n.toLocaleString('vi-VN'); }

            // Mã xác thực cho phiên này — ngẫu nhiên 6 ký tự, lưu vào sessionStorage
            // để giữ nguyên khi reload trong cùng trình duyệt.
            var CODE_KEY = 'nhosub.depositCode';
            var code = null;
            try { code = sessionStorage.getItem(CODE_KEY); } catch (e) {}

            if (!code || !/^[A-HJ-NP-Z2-9]{6}$/.test(code)) {
                var alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
                code = '';
                for (var i = 0; i < 6; i++) {
                    code += alphabet[Math.floor(Math.random() * alphabet.length)];
                }
                try { sessionStorage.setItem(CODE_KEY, code); } catch (e) {}
            }

            function buildContent() {
                var amount = parseInt(amountInput.value, 10);
                var suffix = (amount > 0 ? ' N' + amount : '') + ' ' + code;
                return base + suffix;
            }

            function refresh() {
                var amount = parseInt(amountInput.value, 10);
                var content = buildContent();

                contentEl.textContent = content;
                copyBtn.setAttribute('data-copy', content);

                if (qrImg && bin && account && amount > 0) {
                    var url = 'https://img.vietqr.io/image/' + bin + '-' + account
                        + '-compact2.png?amount=' + amount
                        + '&addInfo=' + encodeURIComponent(content)
                        + '&accountName=' + encodeURIComponent(owner);
                    qrImg.src = url;
                }
            }

            amountInput.addEventListener('input', refresh);
            refresh();
        })();
    </script>
    @endpush
@endsection
