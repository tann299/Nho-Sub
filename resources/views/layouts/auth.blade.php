<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Đăng nhập') — {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" sizes="48x48" href="{{ asset('assets/img/favicon-48.png') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>
<body>
<div class="auth-wrap">
    <aside class="auth-side dot-pattern">
        {{-- Minh hoạ nền: biểu đồ tăng trưởng + tim bay + coin --}}
        <div class="auth-side-art" aria-hidden="true">
            <svg viewBox="0 0 420 260" fill="none">
                <!-- lưới mờ -->
                <g stroke="rgba(255,255,255,.10)" stroke-width="1">
                    <path d="M30 40h360M30 100h360M30 160h360M30 220h360"/>
                </g>
                <!-- cột tăng trưởng mờ phía sau -->
                <g fill="rgba(255,255,255,.12)">
                    <rect x="40"  y="150" width="30" height="70" rx="6"/>
                    <rect x="90"  y="130" width="30" height="90" rx="6"/>
                    <rect x="140" y="140" width="30" height="80" rx="6"/>
                </g>
                <!-- cột chính sáng -->
                <g fill="rgba(255,255,255,.30)">
                    <rect x="190" y="110" width="30" height="110" rx="6"/>
                    <rect x="240" y="80"  width="30" height="140" rx="6"/>
                </g>
                <g fill="#8FC0FF">
                    <rect x="290" y="55" width="30" height="165" rx="6"/>
                </g>
                <!-- đường nối đỉnh -->
                <path d="M55 145 L105 125 L155 135 L205 105 L255 75 L305 50"
                      stroke="#fff" stroke-opacity=".85" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="305" cy="50" r="6" fill="#fff"/>
                <circle cx="305" cy="50" r="13" fill="none" stroke="#fff" stroke-opacity=".35" stroke-width="2.5"/>
                <!-- tim bay -->
                <g transform="translate(330 20)">
                    <path d="M8 14C4 10.6 1.5 7.8 1.5 5 1.5 2.7 3.3 1 5.5 1c1.4 0 2.7.7 3.4 1.9h.2C9.8 1.7 11.1 1 12.5 1 14.7 1 16.5 2.7 16.5 5c0 2.8-2.5 5.6-6.5 9l-2 2Z" fill="#FF7A9C"/>
                </g>
                <!-- coin -->
                <g transform="translate(30 30)">
                    <circle cx="12" cy="12" r="11" fill="#FFC53D"/>
                    <circle cx="12" cy="12" r="8" fill="none" stroke="#fff" stroke-opacity=".55" stroke-width="1.5"/>
                    <path d="M12 6v12M9.5 8.5h3.5a2 2 0 0 1 0 4h-2a2 2 0 0 0 0 4h3.5" stroke="#fff" stroke-width="1.8" stroke-linecap="round" fill="none"/>
                </g>
                <!-- mũi tên nhỏ -->
                <g transform="translate(340 120)">
                    <path d="M2 12 10 4l4 4 8-8" stroke="#7EE2A8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M16 0h6v6" stroke="#7EE2A8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </g>
            </svg>
        </div>

        <div class="auth-side-body">
            <a href="{{ route('landing') }}" class="flex gap-1" style="align-items:center;margin-bottom:28px;color:#fff;">
                <img src="{{ asset('assets/img/logo.png') }}" alt="NhoSub" width="40" height="40" style="border-radius:10px;">
                <strong style="font-family:'Baloo 2',system-ui,sans-serif;font-size:28px;font-weight:800;color:#fff;letter-spacing:.02em;">NHOSUB</strong>
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
                <strong style="font-family:'Baloo 2',system-ui,sans-serif;font-size:22px;font-weight:800;color:var(--primary-dark);letter-spacing:.02em;">NHOSUB</strong>
            </a>
            @include('partials.flash')
            @yield('content')
        </div>
    </main>
</div>
<script src="{{ asset('assets/js/app.js') }}" defer></script>
</body>
</html>
