@extends('layouts.panel')
@section('title', 'Nạp xu TikTok')
@section('content')
    <div class="page-hero">
        <div class="page-hero-body">
            <h2 class="page-hero-title">Nạp xu TikTok</h2>
            <p class="page-hero-desc">Xu nạp qua Napxu. Thanh toán bằng ví NhoSub, không ảnh hưởng đơn tăng tương tác TopSubRe.</p>
        </div>
        <a class="btn btn-outline" href="{{ route('panel.topup.orders') }}">Đơn nạp xu</a>
    </div>
    @if ($packages->isEmpty())
        <div class="card"><h3>Chưa có gói nạp</h3><p>Quản trị viên cần cấu hình mã gói, máy chủ và giá từ Napxu trước khi mở bán.</p></div>
    @elseif ($accounts->isEmpty())
        <div class="card"><h3>Cần liên kết tài khoản TikTok</h3><p>Liên kết qua QR hoặc cookie để chọn tài khoản nhận xu.</p><a class="btn btn-primary" href="{{ route('panel.tiktok.index') }}">Liên kết TikTok</a></div>
    @else
        <form method="POST" action="{{ route('panel.topup.store') }}" class="card" data-guard>
            @csrf
            <input type="hidden" name="client_token" value="{{ $clientToken }}">
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="topup-account">Tài khoản nhận xu</label>
                    <select class="form-select" id="topup-account" name="tiktok_account_id" required>
                        @foreach ($accounts as $account)<option value="{{ $account->id }}" @selected((int) old('tiktok_account_id') === $account->id)>{{ $account->username }} — {{ $account->nickname }}</option>@endforeach
                    </select>
                    <a class="text-sm" href="{{ route('panel.tiktok.index') }}">Quản lý TikTok của tôi</a>
                </div>
                <div class="form-group">
                    <label class="form-label" for="topup-package">Gói xu và giá thanh toán</label>
                    <select class="form-select" id="topup-package" name="topup_package_id" required>
                        @foreach ($packages as $package)<option value="{{ $package->id }}" @selected((int) old('topup_package_id') === $package->id)>{{ $package->name }} — {{ number_format($package->coins, 0, ',', '.') }} xu — {{ number_format($package->sell_price, 0, ',', '.') }} ₫</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="topup-pin">PIN Napxu (nếu tài khoản đại lý đã bật PIN)</label>
                <input class="form-input" id="topup-pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="off" aria-describedby="topup-pin-hint">
                <p class="form-hint" id="topup-pin-hint">Để trống nếu không có PIN. Không nhập mật khẩu TikTok.</p>
            </div>
            <p>Số dư ví NhoSub: <strong>{{ number_format(auth()->user()->balance, 0, ',', '.') }} ₫</strong></p>
            <p class="text-sm text-muted">Kiểm tra đúng tài khoản và giá trước khi xác nhận. Đơn bị từ chối được hoàn về ví; đơn timeout chờ đối soát, không đặt lại.</p>
            <button class="btn btn-primary" type="submit">Xác nhận nạp xu và trừ ví</button>
        </form>
    @endif
@endsection
