<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'NhoSub — Sub nhỏ, lớn dần cùng bạn')</title>
    <meta name="description" content="@yield('meta_description', 'NhoSub — panel tăng tương tác mạng xã hội: like, follow, view, comment, share cho Facebook, TikTok, Instagram, YouTube. Giá tốt, chạy nhanh, hỗ trợ 24/7.')">
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/img/favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>
<body>
<header class="site-header">
    <div class="container site-header-inner">
        <a href="{{ route('landing') }}" class="flex gap-1" style="align-items:center;">
            <img src="{{ asset('assets/img/logo.png') }}" alt="NhoSub" width="34" height="34">
            <strong style="font-family:'Baloo 2',system-ui,sans-serif;font-size:24px;font-weight:800;color:var(--primary-dark);letter-spacing:.02em;">NHOSUB</strong>
        </a>

        <nav class="site-nav">
            <a href="#tinh-nang">Tính năng</a>
            <a href="#bang-gia">Bảng giá</a>
            <a href="#danh-gia">Đánh giá</a>
            <a href="#faq">Câu hỏi thường gặp</a>
        </nav>

        <div class="topbar-spacer"></div>

        @auth
            <a href="{{ route('panel.dashboard') }}" class="btn btn-primary">Vào bảng điều khiển</a>
        @else
            <a href="{{ route('login') }}" class="btn btn-ghost">Đăng nhập</a>
            <a href="{{ route('register') }}" class="btn btn-primary">Đăng ký miễn phí</a>
        @endauth
    </div>
</header>

@yield('content')

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <div class="flex gap-1" style="align-items:center;margin-bottom:12px;">
                    <img src="{{ asset('assets/img/logo.png') }}" alt="NhoSub" width="30" height="30">
                    <strong style="font-family:'Baloo 2',system-ui,sans-serif;font-size:22px;font-weight:800;color:var(--primary-dark);letter-spacing:.02em;">NHOSUB</strong>
                </div>
                <p class="text-muted text-sm">Sub nhỏ, lớn dần cùng bạn. Panel tăng tương tác mạng xã hội cho người Việt.</p>
            </div>
            <div>
                <h4 style="font-size:14px;">Dịch vụ</h4>
                <ul class="footer-links">
                    <li><a href="#bang-gia">Facebook</a></li>
                    <li><a href="#bang-gia">TikTok</a></li>
                    <li><a href="#bang-gia">Instagram</a></li>
                    <li><a href="#bang-gia">YouTube</a></li>
                </ul>
            </div>
            <div>
                <h4 style="font-size:14px;">Hỗ trợ</h4>
                <ul class="footer-links">
                    <li><a href="#faq">Câu hỏi thường gặp</a></li>
                    <li><a href="{{ route('login') }}">Đăng nhập</a></li>
                    <li><a href="{{ route('register') }}">Đăng ký</a></li>
                </ul>
            </div>
            <div>
                <h4 style="font-size:14px;">Liên hệ</h4>
                <ul class="footer-links">
                    <li>Email: hotro@nhosub.vn</li>
                    <li>Hỗ trợ: 8:00 – 22:00 hằng ngày</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <span>© {{ date('Y') }} NhoSub. Bảo lưu mọi quyền.</span>
            <span>Thanh toán an toàn · Hỗ trợ tiếng Việt</span>
        </div>
    </div>
</footer>
<script src="{{ asset('assets/js/app.js') }}" defer></script>
</body>
</html>
