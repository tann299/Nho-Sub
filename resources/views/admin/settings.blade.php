@extends('layouts.panel')
@section('title', 'Cấu hình hệ thống')
@section('content')
    <form method="POST" action="{{ route('admin.settings.update') }}" data-guard>
        @csrf

        <div class="grid grid-2">
            <div class="card">
                <h3 class="card-title">Giá & lợi nhuận</h3>
                <div class="form-group">
                    <label class="form-label" for="exchange_rate">Tỉ giá USD → VND</label>
                    <input class="form-input" id="exchange_rate" name="exchange_rate" type="number" value="{{ $exchangeRate }}" required>
                    <div class="form-hint">Mặc định 26.000.</div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="default_markup_percent">% lợi nhuận mặc định</label>
                    <input class="form-input" id="default_markup_percent" name="default_markup_percent" type="number" step="0.01"
                           value="{{ $markupPercent }}" required>
                    <div class="form-hint">Giá bán = giá gốc × (1 + %). Mặc định 30.</div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="min_deposit">Mức nạp tối thiểu (₫)</label>
                    <input class="form-input" id="min_deposit" name="min_deposit" type="number" value="{{ $minDeposit }}" required>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label" for="provider_balance_alert">Ngưỡng cảnh báo số dư NCC (USD)</label>
                    <input class="form-input" id="provider_balance_alert" name="provider_balance_alert" type="number" step="0.01"
                           value="{{ $providerAlert }}">
                </div>
            </div>

            <div class="card">
                <h3 class="card-title">Ngân hàng nhận chuyển khoản</h3>
                <div class="form-group">
                    <label class="form-label" for="bank_name">Tên ngân hàng</label>
                    <input class="form-input" id="bank_name" name="bank_name" value="{{ $bankName }}">
                </div>
                <div class="form-group">
                    <label class="form-label" for="bank_account">Số tài khoản</label>
                    <input class="form-input" id="bank_account" name="bank_account" value="{{ $bankAccount }}">
                </div>
                <div class="form-group">
                    <label class="form-label" for="bank_owner">Chủ tài khoản</label>
                    <input class="form-input" id="bank_owner" name="bank_owner" value="{{ $bankOwner }}">
                </div>
                <div class="form-group mb-0">
                    <label class="form-label" for="bank_bin">Mã BIN (dùng tạo QR VietQR)</label>
                    <input class="form-input" id="bank_bin" name="bank_bin" value="{{ $bankBin }}">
                    <div class="form-hint">Ví dụ MB Bank = 970422.</div>
                </div>
            </div>
        </div>

        <div class="grid grid-2 mt-3">
            <div class="card">
                <h3 class="card-title">Nhà cung cấp dịch vụ</h3>
                <div class="form-group">
                    <label class="form-label" for="provider_driver">NCC dùng cho đặt đơn</label>
                    <select class="form-select" id="provider_driver" name="provider_driver">
                        <option value="topsubre" @selected($providerDriver === 'topsubre')>TopSubRe (likenhanh.pro)</option>
                        <option value="napxu" @selected($providerDriver === 'napxu')>Napxu (napxu.vn)</option>
                    </select>
                    <div class="form-hint">Chế độ "Tự động" bên dưới chọn NCC này khi key của nó đã nhập.</div>
                </div>
            </div>
        </div>

        <div class="grid grid-2 mt-3">
            <div class="card">
                <h3 class="card-title">TopSubRe</h3>
                <div class="form-group">
                    <label class="form-label" for="topsubre_api_key">API key</label>
                    <input class="form-input" id="topsubre_api_key" name="topsubre_api_key" type="password"
                           placeholder="{{ $masked['topsubre_api_key'] ?? 'Chưa cấu hình' }}">
                    <div class="form-hint">Để trống để giữ key hiện tại. Key được mã hoá khi lưu.</div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="demo_mode">Chế độ nhà cung cấp</label>
                    <select class="form-select" id="demo_mode" name="demo_mode">
                        <option value="auto" @selected($demoMode === 'auto')>Tự động (có key → thật, không có → demo)</option>
                        <option value="on" @selected($demoMode === 'on')>Bắt buộc demo</option>
                        <option value="off" @selected($demoMode === 'off')>Bắt buộc dùng API thật</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="demo_scenario">Kịch bản demo</label>
                    <select class="form-select" id="demo_scenario" name="demo_scenario">
                        <option value="auto" @selected($demoScenario === 'auto')>Tự động (hoàn thành)</option>
                        <option value="partial" @selected($demoScenario === 'partial')>Chạy một phần</option>
                        <option value="cancel" @selected($demoScenario === 'cancel')>Huỷ đơn</option>
                        <option value="fail" @selected($demoScenario === 'fail')>Nhà cung cấp từ chối</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="topsubre_pinned_ip">IP ghim TopSubRe (chỉ khi DNS bị chặn)</label>
                    <input class="form-input" id="topsubre_pinned_ip" name="topsubre_pinned_ip"
                           value="{{ $pinnedIp }}" placeholder="Để trống nếu kết nối bình thường">
                    <div class="form-hint">
                        Chỉ dùng khi báo lỗi "Truy cập bị chặn bởi bộ lọc mạng".
                        Lấy IP thật tại <span class="mono">https://dns.google/resolve?name=likenhanh.pro&amp;type=A</span>
                        rồi nhập một trong các IP trả về.
                    </div>
                </div>
            </div>

            <div class="card">
                <h3 class="card-title">Napxu (napxu.vn)</h3>
                <div class="form-group">
                    <label class="form-label" for="napxu_api_key">API key</label>
                    <input class="form-input" id="napxu_api_key" name="napxu_api_key" type="password"
                           placeholder="{{ $masked['napxu_api_key'] ?? 'Chưa cấu hình' }}">
                    <div class="form-hint">
                        Key dạng <span class="mono">napx_...</span>, lấy tại napxu.vn → Tài Liệu API.
                        Để trống để giữ key hiện tại. Key được mã hoá khi lưu.
                    </div>
                </div>
                <div class="form-group mb-0">
                    <div class="form-hint">
                        Dịch vụ napxu tính giá theo đơn vị (VND/lượt) — hệ thống quy về USD/1000 lượt
                        bằng tỉ giá ở trên khi đồng bộ. Trạng thái đơn tra qua danh sách 100 đơn mới nhất.
                    </div>
                </div>
            </div>

            <div class="card">
                <h3 class="card-title">Cổng thanh toán</h3>

                <div class="form-group">
                    <label class="text-sm flex gap-1" style="align-items:center;">
                        <input type="checkbox" name="sepay_enabled" value="1" @checked($sepayEnabled)> Bật SePay
                    </label>
                </div>
                <div class="form-group">
                    <label class="form-label" for="sepay_api_key">SePay webhook key</label>
                    <input class="form-input" id="sepay_api_key" name="sepay_api_key" type="password"
                           placeholder="{{ $masked['sepay_api_key'] ?? 'Chưa cấu hình' }}">
                </div>

                <hr class="divider">

                <div class="form-group">
                    <label class="text-sm flex gap-1" style="align-items:center;">
                        <input type="checkbox" name="payos_enabled" value="1" @checked($payosEnabled)> Bật PayOS
                    </label>
                </div>
                <div class="form-group">
                    <label class="form-label" for="payos_client_id">PayOS Client ID</label>
                    <input class="form-input" id="payos_client_id" name="payos_client_id" type="password"
                           placeholder="{{ $masked['payos_client_id'] ?? 'Chưa cấu hình' }}">
                </div>
                <div class="form-group">
                    <label class="form-label" for="payos_api_key">PayOS API Key</label>
                    <input class="form-input" id="payos_api_key" name="payos_api_key" type="password"
                           placeholder="{{ $masked['payos_api_key'] ?? 'Chưa cấu hình' }}">
                </div>
                <div class="form-group mb-0">
                    <label class="form-label" for="payos_checksum_key">PayOS Checksum Key</label>
                    <input class="form-input" id="payos_checksum_key" name="payos_checksum_key" type="password"
                           placeholder="{{ $masked['payos_checksum_key'] ?? 'Chưa cấu hình' }}">
                </div>
            </div>
        </div>

        <button class="btn btn-primary mt-3" type="submit">Lưu cấu hình</button>
    </form>
@endsection
