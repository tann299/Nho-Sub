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
                {{-- Cảnh báo realtime khi nhập dưới mức tối thiểu --}}
                <div class="deposit-warn" id="deposit-warn" hidden>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                    <span id="deposit-warn-text"></span>
                </div>
            </div>

            {{-- Trạng thái nạp tiền realtime: chờ / thành công / thất bại --}}
            <div class="deposit-status" id="deposit-status" hidden>
                <span class="deposit-status-dot" id="deposit-status-dot"></span>
                <span id="deposit-status-text">Đang chờ giao dịch...</span>
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
                    <tr><th>Số tiền</th><th>Phương thức</th><th>Trạng thái</th><th>Ghi chú</th><th>Thời gian</th></tr>
                </thead>
                <tbody>
                @forelse ($requests as $r)
                    <tr>
                        <td class="num"><strong>{{ number_format($r['amount'], 0, ',', '.') }} ₫</strong></td>
                        <td>{{ $r['method'] }}</td>
                        <td>@include('partials.order-status', ['status' => $r['status']])</td>
                        <td class="text-sm text-muted">{{ $r['note'] ?: '—' }}</td>
                        <td class="text-sm text-muted">{{ $r['time']?->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="padding:0;border:none;">
                            <div class="empty-block" style="border:none;border-radius:0;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M12 3v12m0 0l4-4m-4 4l-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                                <div class="empty-title">Chưa có giao dịch nạp nào</div>
                                <p>Lịch sử nạp (tự động + duyệt tay) sẽ hiện đầy đủ ở đây sau giao dịch đầu tiên.</p>
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
            var base = @json($transferContent);
            var bin = @json($bankBin);
            var account = @json($bankAccount);
            var owner = @json($bankOwner);

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

                // Cảnh báo ngay khi nhập dưới mức tối thiểu
                updateWarning(amount);

                if (qrImg && bin && account && amount > 0) {
                    var url = 'https://img.vietqr.io/image/' + bin + '-' + account
                        + '-compact2.png?amount=' + amount
                        + '&addInfo=' + encodeURIComponent(content)
                        + '&accountName=' + encodeURIComponent(owner);
                    qrImg.src = url;
                }
            }

            // ---------- Cảnh báo dưới mức tối thiểu ----------
            var warnBox = document.getElementById('deposit-warn');
            var warnText = document.getElementById('deposit-warn-text');
            var minDeposit = @json($minDeposit);

            function updateWarning(amount) {
                if (!warnBox) return;
                if (amount > 0 && amount < minDeposit) {
                    warnText.textContent = 'Số tiền dưới mức tối thiểu '
                        + fmt(minDeposit) + ' ₫ — chuyển khoản số tiền này sẽ KHÔNG được cộng vào ví.';
                    warnBox.hidden = false;
                } else {
                    warnBox.hidden = true;
                }
            }

            // ---------- Trạng thái nạp tiền realtime ----------
            // Poll webhook log mới nhất mỗi 5 giây khi trang đang mở:
            // nếu giao dịch khớp mã xác thực của phiên này → hiện thành công/thất bại.
            var statusBox = document.getElementById('deposit-status');
            var statusDot = document.getElementById('deposit-status-dot');
            var statusText = document.getElementById('deposit-status-text');
            var pollTimer = null;

            function setStatus(kind, text) {
                if (!statusBox) return;
                statusBox.hidden = false;
                statusBox.className = 'deposit-status deposit-status-' + kind;
                statusText.textContent = text;
            }

            function startPolling() {
                if (!statusBox || pollTimer) return;
                statusBox.hidden = false;
                statusDot.className = 'deposit-status-dot pulse';
                setStatus('waiting', 'Đang chờ giao dịch — quét QR hoặc chuyển khoản theo nội dung trên. Trang tự kiểm tra mỗi 5 giây.');

                pollTimer = setInterval(function () {
                    fetch('{{ route('panel.deposit.status') }}', {headers: {'Accept': 'application/json'}})
                        .then(function (r) { return r.ok ? r.json() : null; })
                        .then(function (data) {
                            if (!data) return;
                            if (data.status === 'processed') {
                                setStatus('success', '✓ Nạp thành công ' + fmt(data.amount) + ' ₫ — ví đã được cộng tiền.');
                                stopPolling();
                            } else if (data.status === 'rejected' || data.status === 'unmatched') {
                                setStatus('fail', data.status === 'rejected'
                                    ? '✗ Giao dịch bị từ chối: ' + (data.reason || 'sai thông tin.') + ' Liên hệ hỗ trợ nếu cần.'
                                    : '✗ Chưa khớp giao dịch nào với mã xác thực của bạn. Kiểm tra nội dung CK hoặc liên hệ hỗ trợ.');
                                stopPolling();
                            }
                        })
                        .catch(function () { /* lỗi mạng: thử lần sau */ });
                }, 5000);
            }

            function stopPolling() {
                if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
            }

            // Bắt đầu theo dõi ngay khi vào trang nạp tiền
            startPolling();
            // Dừng khi rời trang
            window.addEventListener('beforeunload', stopPolling);

            amountInput.addEventListener('input', refresh);
            refresh();
        })();
    </script>
    @endpush
@endsection
