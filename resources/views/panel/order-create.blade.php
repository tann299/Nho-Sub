@extends('layouts.panel')
@section('title', 'Đặt đơn mới')
@section('content')
    <div class="page-hero">
        <div class="page-hero-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        </div>
        <div class="page-hero-body">
            <h2 class="page-hero-title">Đặt đơn mới</h2>
            <p class="page-hero-desc">Chọn dịch vụ, dán link, xem trước tổng tiền rồi xác nhận.</p>
        </div>
        <div class="page-hero-action stat stat-stack" style="padding:8px 16px;text-align:right;">
            <p class="stat-label">Số dư khả dụng</p>
            <div class="stat-value primary" style="font-size:17px;">{{ number_format(auth()->user()->balance, 0, ',', '.') }} ₫</div>
        </div>
    </div>

    {{-- Thông tin dịch vụ đang chọn: mã + trạng thái hoạt động --}}
    <div class="card mb-2" id="svc-info-card" style="display:none;">
        <div class="flex-between flex-wrap gap-2">
            <div>
                <span class="card-label">Dịch vụ đang chọn</span>
                <div id="svc-info-name" style="font-weight:700;font-size:14.5px;">—</div>
                <div class="text-sm text-muted mt-1">
                    Mã dịch vụ: <span class="mono" id="svc-info-code">—</span>
                </div>
            </div>
            <div class="flex gap-1 flex-wrap">
                <span class="badge badge-completed" id="svc-status-badge">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M20 6L9 17l-5-5"/></svg>
                    Đang hoạt động
                </span>
                <span class="badge badge-neutral" id="svc-minmax-badge">—</span>
                <span class="badge badge-processing" id="svc-speed-badge">—</span>
            </div>
        </div>

        {{-- Ghi chú từ admin về dịch vụ --}}
        <div class="note-block mt-2" id="svc-note-block" style="display:none;margin-bottom:0;">
            <strong>Lưu ý:</strong> <span id="svc-note-text"></span>
        </div>
    </div>

    <form method="POST" action="{{ route('panel.orders.preview') }}" class="card" style="max-width:720px;">
        @csrf
        <input type="hidden" name="client_token" value="{{ $clientToken }}">

        <div class="form-group">
            <label class="form-label" for="service_id">Chọn dịch vụ</label>
            <select class="form-select @error('service_id') is-invalid @enderror" id="service_id" name="service_id" required>
                <option value="">— Chọn dịch vụ —</option>
                @foreach ($categories as $category)
                    <optgroup label="{{ $category->name }}">
                        @foreach ($category->services as $service)
                            <option value="{{ $service->id }}"
                                    data-min="{{ $service->min_qty }}"
                                    data-max="{{ $service->max_qty }}"
                                    data-price="{{ $service->sell_price }}"
                                    data-name="{{ $service->name }}"
                                    data-speed="{{ $service->speed ?? '' }}"
                                    data-desc="{{ $service->description ?? '' }}"
                                    @selected($preselected === $service->id)>
                                {{ $service->name }} — {{ number_format($service->sell_price, 0, ',', '.') }} ₫/1.000
                            </option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" for="target_link">Link cần tăng tương tác</label>
            <input class="form-input @error('target_link') is-invalid @enderror" id="target_link" name="target_link"
                   value="{{ old('target_link') }}" placeholder="https://facebook.com/..." required>
            <div class="form-hint">Dán link bài viết, trang hoặc video <strong>công khai</strong>. Link riêng tư → đơn không chạy và không hoàn tiền.</div>
        </div>

        <div class="form-group">
            <label class="form-label" for="quantity">Số lượng</label>
            <input class="form-input @error('quantity') is-invalid @enderror" id="quantity" name="quantity"
                   type="number" value="{{ old('quantity') }}" required>
            <div class="form-hint" id="qty-hint">Chọn dịch vụ để xem giới hạn số lượng.</div>
        </div>

        <div class="form-group">
            <label class="form-label" for="note">Ghi chú (nếu cần)</label>
            <textarea class="form-textarea" id="note" name="note" style="min-height:70px;"
                      placeholder="Ví dụ: nội dung bình luận, yêu cầu riêng…">{{ old('note') }}</textarea>
            <div class="form-hint">Với dịch vụ bình luận tùy chỉnh: mỗi nội dung 1 dòng.</div>
        </div>

        {{-- Tổng kết đơn hàng theo mẫu --}}
        <div class="note-block" style="background:var(--surface-deep);border-color:var(--surface-deep);color:#fff;padding:16px 18px;">
            <div class="flex-between" style="margin-bottom:6px;">
                <span style="color:rgba(255,255,255,.7);font-size:12.5px;">Số lượng</span>
                <strong id="sum-qty" style="font-variant-numeric:tabular-nums;">0</strong>
            </div>
            <div class="flex-between" style="margin-bottom:6px;">
                <span style="color:rgba(255,255,255,.7);font-size:12.5px;">Đơn giá</span>
                <strong id="sum-price" style="font-variant-numeric:tabular-nums;">0 ₫ / 1.000</strong>
            </div>
            <div class="flex-between" style="padding-top:8px;border-top:1px solid rgba(255,255,255,.15);">
                <strong style="font-size:14px;">Tổng thanh toán</strong>
                <strong id="sum-total" style="font-size:17px;color:#DCEBFD;font-variant-numeric:tabular-nums;">0 ₫</strong>
            </div>
            <div class="flex-between mt-1">
                <span style="color:rgba(255,255,255,.6);font-size:12px;">Số dư sau khi đặt</span>
                <span id="sum-balance" style="color:rgba(255,255,255,.85);font-size:12.5px;font-variant-numeric:tabular-nums;">
                    {{ number_format(auth()->user()->balance, 0, ',', '.') }} ₫
                </span>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block mt-2" style="min-height:46px;">
            Xem trước & xác nhận
        </button>
    </form>

    @push('scripts')
    <script>
        (function () {
            var select = document.getElementById('service_id');
            var qty = document.getElementById('quantity');
            var hint = document.getElementById('qty-hint');
            var infoCard = document.getElementById('svc-info-card');
            var infoName = document.getElementById('svc-info-name');
            var infoCode = document.getElementById('svc-info-code');
            var minmaxBadge = document.getElementById('svc-minmax-badge');
            var speedBadge = document.getElementById('svc-speed-badge');
            var noteBlock = document.getElementById('svc-note-block');
            var noteText = document.getElementById('svc-note-text');
            var sumQty = document.getElementById('sum-qty');
            var sumPrice = document.getElementById('sum-price');
            var sumTotal = document.getElementById('sum-total');
            var sumBalance = document.getElementById('sum-balance');
            var BALANCE = {{ (int) auth()->user()->balance }};

            function fmt(n) { return n.toLocaleString('vi-VN'); }

            function refresh() {
                var opt = select.options[select.selectedIndex];
                if (!opt || !opt.value) {
                    hint.textContent = 'Chọn dịch vụ để xem giới hạn số lượng.';
                    infoCard.style.display = 'none';
                    sumQty.textContent = '0';
                    sumPrice.textContent = '0 ₫ / 1.000';
                    sumTotal.textContent = '0 ₫';
                    sumBalance.textContent = fmt(BALANCE) + ' ₫';
                    return;
                }

                var min = parseInt(opt.dataset.min, 10);
                var max = parseInt(opt.dataset.max, 10);
                var price = parseInt(opt.dataset.price, 10);

                qty.min = min;
                qty.max = max;
                hint.textContent = 'Từ ' + fmt(min) + ' đến ' + fmt(max) + '.';

                // Thẻ thông tin dịch vụ: mã + trạng thái
                infoCard.style.display = 'block';
                infoName.textContent = opt.dataset.name;
                infoCode.textContent = 'SV-' + opt.value;
                minmaxBadge.textContent = fmt(min) + ' – ' + fmt(max);
                speedBadge.textContent = opt.dataset.speed || 'Tốc độ chuẩn';

                // Ghi chú = mô tả của dịch vụ (admin nhập khi đồng bộ)
                if (opt.dataset.desc && opt.dataset.desc.length > 5) {
                    noteBlock.style.display = 'block';
                    noteText.textContent = opt.dataset.desc;
                } else {
                    noteBlock.style.display = 'none';
                }

                // Tổng kết
                var q = parseInt(qty.value, 10);
                var total = (!isNaN(q) && q > 0) ? Math.floor(price * q / 1000) : 0;

                sumQty.textContent = isNaN(q) ? '0' : fmt(q);
                sumPrice.textContent = fmt(price) + ' ₫ / 1.000';
                sumTotal.textContent = fmt(total) + ' ₫';
                sumBalance.textContent = fmt(BALANCE - total) + ' ₫';
            }

            select.addEventListener('change', refresh);
            qty.addEventListener('input', refresh);
            refresh();
        })();
    </script>
    @endpush
@endsection
