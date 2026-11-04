@extends('layouts.panel')
@section('title', 'Hồ sơ cá nhân')
@section('content')
    {{-- Header kiểu thẻ: logo chữ cái + tên + vai trò --}}
    <div class="page-hero page-hero-deep dot-pattern" style="padding:24px 22px;">
        <div class="profile-avatar">{{ mb_strtoupper(mb_substr($user->username, 0, 1)) }}</div>
        <div class="page-hero-body">
            <h2 class="page-hero-title">{{ $user->full_name ?: $user->username }}</h2>
            <p class="page-hero-desc">
                {{ $user->username }}
                @if ($user->isAdmin())
                    <span class="badge badge-processing" style="margin-left:6px;">Quản trị viên</span>
                @else
                    <span class="badge badge-completed" style="margin-left:6px;">Khách hàng</span>
                @endif
            </p>
        </div>
        <div class="page-hero-action stat stat-stack" style="background:rgba(255,255,255,.1);border:none;padding:8px 16px;text-align:right;">
            <p class="stat-label" style="color:rgba(255,255,255,.7);">Số dư ví</p>
            <div class="stat-value" style="font-size:17px;color:#fff;">{{ number_format($user->balance, 0, ',', '.') }} ₫</div>
        </div>
    </div>

    {{-- Thông kê nhanh của tài khoản --}}
    <div class="grid grid-3 mb-3">
        <div class="stat">
            <div class="stat-icon success">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 3v12m0 0l4-4m-4 4l-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
            </div>
            <div class="stat-body">
                <p class="stat-label">Tổng đã nạp</p>
                <div class="stat-value success" style="font-size:17px;">{{ number_format($totalDeposited, 0, ',', '.') }} ₫</div>
            </div>
        </div>
        <div class="stat">
            <div class="stat-icon warning">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/></svg>
            </div>
            <div class="stat-body">
                <p class="stat-label">Số đơn đã đặt</p>
                <div class="stat-value warning" style="font-size:17px;">{{ number_format($orderCount) }}</div>
            </div>
        </div>
        <div class="stat">
            <div class="stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
            </div>
            <div class="stat-body">
                <p class="stat-label">Đăng nhập gần nhất</p>
                <div class="stat-value" style="font-size:13.5px;line-height:2.2;">{{ $user->last_login_at?->format('d/m/Y H:i') ?? '—' }}</div>
            </div>
        </div>
    </div>

    <div class="grid grid-2">
        {{-- Sửa thông tin --}}
        <div class="card">
            <span class="card-label">Thông tin cá nhân</span>

            <div class="note-block">
                Tên đăng nhập <strong>{{ $user->username }}</strong> không thể thay đổi.
            </div>

            <form method="POST" action="{{ route('panel.profile.update') }}" data-guard>
                @csrf
                <div class="form-group">
                    <label class="form-label" for="full_name">Họ và tên</label>
                    <input class="form-input" id="full_name" name="full_name" value="{{ old('full_name', $user->full_name) }}">
                </div>
                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-input @error('email') is-invalid @enderror" id="email" name="email"
                           type="email" value="{{ old('email', $user->email) }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="phone">Số điện thoại</label>
                    <input class="form-input" id="phone" name="phone" value="{{ old('phone', $user->phone) }}">
                </div>
                <button class="btn btn-primary" type="submit">Lưu thay đổi</button>
            </form>
        </div>

        {{-- Thông tin tài khoản (chỉ đọc) --}}
        <div>
            <div class="card mb-2">
                <span class="card-label">Thông tin tài khoản</span>
                <div class="copy-row">
                    <span class="copy-label">Tên đăng nhập</span>
                    <span class="copy-value mono">{{ $user->username }}</span>
                </div>
                <div class="copy-row">
                    <span class="copy-label">Ngày tham gia</span>
                    <span class="copy-value">{{ $user->created_at?->format('d/m/Y') ?? '—' }}</span>
                </div>
                <div class="copy-row">
                    <span class="copy-label">Trạng thái</span>
                    <span class="copy-value">
                        <span class="badge {{ $user->status === 'active' ? 'badge-completed' : 'badge-canceled' }}">
                            {{ $user->status === 'active' ? 'Hoạt động' : 'Đã khoá' }}
                        </span>
                    </span>
                </div>
                <div class="copy-row" style="margin-bottom:0;">
                    <span class="copy-label">Nội dung CK riêng</span>
                    <span class="copy-value mono" style="color:var(--primary-dark);">{{ $user->transferContent() }}</span>
                </div>
            </div>

            <div class="card" style="padding:0;overflow:hidden;">
                <div style="padding:14px 16px 0;"><span class="card-label">Bảo mật</span></div>
                <div style="padding:0 16px 16px;">
                    <p class="text-sm text-muted mb-2">Đổi mật khẩu thường xuyên để bảo vệ ví của bạn.</p>
                    <a href="{{ route('panel.profile.password') }}" class="btn btn-outline btn-block">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M7 11V7a5 5 0 0 1 10 0v4M5 11h14v10H5z"/></svg>
                        Đổi mật khẩu
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
