@extends('layouts.panel')
@section('title', 'Đặt đơn mới')
@section('content')
    <div class="page-hero">
        <div class="page-hero-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        </div>
        <div class="page-hero-body">
            <h2 class="page-hero-title">Đặt đơn mới</h2>
            <p class="page-hero-desc">3 trường, 1 lần xác nhận — tổng tiền hiện trước khi trừ ví.</p>
        </div>
        <div class="page-hero-action stat stat-stack" style="padding:8px 16px;text-align:right;">
            <p class="stat-label">Số dư khả dụng</p>
            <div class="stat-value primary" style="font-size:17px;">{{ number_format(auth()->user()->balance, 0, ',', '.') }} ₫</div>
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
            <div class="form-hint">Dán link bài viết, trang hoặc video công khai.</div>
        </div>

        <div class="form-group">
            <label class="form-label" for="quantity">Số lượng</label>
            <input class="form-input @error('quantity') is-invalid @enderror" id="quantity" name="quantity"
                   type="number" value="{{ old('quantity') }}" required>
            <div class="form-hint" id="qty-hint">Chọn dịch vụ để xem giới hạn số lượng.</div>
        </div>

        <div class="alert alert-info" id="price-preview" style="display:none;">
            Tạm tính: <strong id="price-total">0 ₫</strong>
        </div>

        <button type="submit" class="btn btn-primary">Xem trước đơn hàng</button>
    </form>

    @push('scripts')
    <script>
        (function () {
            var select = document.getElementById('service_id');
            var qty = document.getElementById('quantity');
            var hint = document.getElementById('qty-hint');
            var box = document.getElementById('price-preview');
            var total = document.getElementById('price-total');

            function refresh() {
                var opt = select.options[select.selectedIndex];
                if (!opt || !opt.value) { hint.textContent = 'Chọn dịch vụ để xem giới hạn số lượng.'; box.style.display = 'none'; return; }

                var min = parseInt(opt.dataset.min, 10);
                var max = parseInt(opt.dataset.max, 10);
                var price = parseInt(opt.dataset.price, 10);

                qty.min = min;
                qty.max = max;
                hint.textContent = 'Từ ' + min.toLocaleString('vi-VN') + ' đến ' + max.toLocaleString('vi-VN') + '.';

                var q = parseInt(qty.value, 10);
                if (!isNaN(q) && q > 0) {
                    box.style.display = 'block';
                    total.textContent = Math.floor(price * q / 1000).toLocaleString('vi-VN') + ' ₫';
                }
            }

            select.addEventListener('change', refresh);
            qty.addEventListener('input', refresh);
            refresh();
        })();
    </script>
    @endpush
@endsection
