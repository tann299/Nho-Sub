<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Bảng điều khiển') — {{ config('app.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/img/favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ file_exists(public_path('assets/css/app.css')) ? filemtime(public_path('assets/css/app.css')) : '1' }}">
</head>
<body>
<div class="layout">
    <aside class="sidebar" id="sidebar">
        <a href="{{ route('panel.dashboard') }}" class="sidebar-brand">
            <img src="{{ asset('assets/img/logo.svg') }}" alt="NhoSub">
            <span class="sidebar-brand-text">
                NhoSub
                <span class="sidebar-brand-sub">Sub nhỏ, lớn dần cùng bạn</span>
            </span>
        </a>

        <nav class="sidebar-nav">
            @include('partials.sidebar-menu')
        </nav>
    </aside>

    <div class="sidebar-overlay"></div>

    <div class="main">
        <header class="topbar">
            <button class="icon-btn" data-sidebar-toggle aria-label="Mở menu" type="button">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
            </button>

            <h1 class="topbar-title">@yield('title', 'Tổng quan')</h1>

            <div class="topbar-spacer"></div>

            @if (Route::has('panel.wallet.index'))
                <a href="{{ route('panel.wallet.index') }}" class="topbar-balance" title="Số dư ví">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 7a2 2 0 0 1 2-2h13a1 1 0 0 1 1 1v3"/><path d="M3 7v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H5"/><circle cx="17" cy="14" r="1"/></svg>
                    {{ number_format(auth()->user()->balance, 0, ',', '.') }} ₫
                </a>
            @endif

            @if (Route::has('panel.notifications.index'))
                <a href="{{ route('panel.notifications.index') }}" class="icon-btn" aria-label="Thông báo">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                    @if (($unreadCount ?? 0) > 0)
                        <span class="dot"></span>
                    @endif
                </a>
            @endif

            <div data-dropdown style="position:relative;">
                <button class="icon-btn" data-dropdown-trigger aria-label="Tài khoản" type="button">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                </button>
                <div data-dropdown-menu class="card" style="display:none;position:absolute;right:0;top:52px;min-width:220px;padding:8px;z-index:60;">
                    <div style="padding:8px 10px;border-bottom:1px solid #EDF2FA;margin-bottom:6px;">
                        <strong>{{ auth()->user()->username }}</strong>
                        <div class="text-sm text-muted">{{ auth()->user()->email }}</div>
                    </div>
                    @if (Route::has('panel.profile.edit'))
                        <a class="nav-item" href="{{ route('panel.profile.edit') }}">Hồ sơ cá nhân</a>
                    @endif
                    @if (Route::has('panel.profile.password'))
                        <a class="nav-item" href="{{ route('panel.profile.password') }}">Đổi mật khẩu</a>
                    @endif
                    @if (auth()->user()->isAdmin() && Route::has('admin.dashboard'))
                        <a class="nav-item" href="{{ route('admin.dashboard') }}">Trang quản trị</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="nav-item" style="width:100%;border:0;background:none;cursor:pointer;text-align:left;font:inherit;">Đăng xuất</button>
                    </form>
                </div>
            </div>
        </header>

        <main class="content">
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</div>

<div class="toast-wrap"></div>
<script src="{{ asset('assets/js/app.js') }}" defer></script>
@stack('scripts')
</body>
</html>
