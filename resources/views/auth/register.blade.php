@extends('layouts.auth')
@section('title', 'Đăng ký')
@section('content')
    <h1 class="auth-title">Tạo tài khoản</h1>
    <p class="auth-sub">Miễn phí, chỉ mất 30 giây.</p>

    <form method="POST" action="{{ route('register.store') }}" data-guard>
        @csrf
        <div class="form-group">
            <label class="form-label" for="username">Tên đăng nhập</label>
            <input class="form-input @error('username') is-invalid @enderror" id="username" name="username"
                   value="{{ old('username') }}" required autofocus autocomplete="username" maxlength="32">
            <div class="form-hint">3–32 ký tự, chỉ gồm chữ, số và dấu gạch dưới.</div>
        </div>
        <div class="form-group">
            <label class="form-label" for="email">Email</label>
            <input class="form-input @error('email') is-invalid @enderror" id="email" name="email"
                   type="email" value="{{ old('email') }}" required autocomplete="email">
        </div>
        <div class="form-group">
            <label class="form-label" for="full_name">Họ và tên</label>
            <input class="form-input" id="full_name" name="full_name" value="{{ old('full_name') }}" autocomplete="name">
        </div>
        <div class="form-group">
            <label class="form-label" for="phone">Số điện thoại</label>
            <input class="form-input" id="phone" name="phone" value="{{ old('phone') }}" autocomplete="tel">
        </div>
        <div class="form-group">
            <label class="form-label" for="password">Mật khẩu</label>
            <input class="form-input @error('password') is-invalid @enderror" id="password" name="password"
                   type="password" required autocomplete="new-password">
            <div class="form-hint">Tối thiểu 8 ký tự.</div>
        </div>
        <div class="form-group">
            <label class="form-label" for="password_confirmation">Nhập lại mật khẩu</label>
            <input class="form-input" id="password_confirmation" name="password_confirmation"
                   type="password" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Tạo tài khoản</button>
    </form>

    <p class="text-center text-sm mt-3 mb-0">
        Đã có tài khoản? <a href="{{ route('login') }}" class="link-underline">Đăng nhập</a>
    </p>
@endsection
