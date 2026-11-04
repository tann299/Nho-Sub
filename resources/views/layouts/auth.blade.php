<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Đăng nhập') — {{ config('app.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/img/favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>
<body>
<div class="auth-wrap">
    <aside class="auth-side dot-pattern">
        <div style="position:relative;z-index:1;max-width:420px;">
            <a href="{{ route('landing') }}" class="flex gap-1" style="align-items:center;margin-bottom:28px;color:#fff;">
                <img src="{{ asset('assets/img/logo.png') }}" alt="NhoSub" width="40" height="40" style="border-radius:10px;">
                <strong style="font-size:24px;color:#fff;letter-spacing:.01em;">NHOSUB</strong>
            </a>
            <h2 style="font-size:30px;">Sub nhỏ,<br>lớn dần cùng bạn</h2>
            <p>Nạp tiền một lần, đặt đơn mọi lúc. Đơn hàng được đẩy đi tự động và cập nhật trạng thái mỗi 5 phút.</p>
            <ul style="list-style:none;padding:0;margin:24px 0 0;">
                <li style="margin-bottom:10px;">✓ Giá hiển thị rõ ràng theo 1.000 lượt</li>
                <li style="margin-bottom:10px;">✓ Tự động hoàn tiền phần chưa chạy</li>
                <li style="margin-bottom:10px;">✓ Hỗ trợ tiếng Việt 8:00 – 22:00</li>
            </ul>
        </div>
    </aside>

    <main class="auth-main">
        <div class="auth-card">
            <a href="{{ route('landing') }}" class="flex gap-1 mb-3" style="align-items:center;">
                <img src="{{ asset('assets/img/logo.png') }}" alt="NhoSub" width="32" height="32">
                <strong style="font-size:19px;color:var(--primary-dark);letter-spacing:.01em;">NHOSUB</strong>
            </a>
            @include('partials.flash')
            @yield('content')
        </div>
    </main>
</div>
<script src="{{ asset('assets/js/app.js') }}" defer></script>
</body>
</html>
