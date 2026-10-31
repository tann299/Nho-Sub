@extends('layouts.auth')
@section('title', 'Đăng nhập')
@section('content')
    <h1 class="auth-title">Đăng nhập</h1>
    <p class="auth-sub">Chào mừng bạn quay lại NhoSub.</p>

    <form method="POST" action="{{ route('login.store') }}" data-guard>
        @csrf
        <div class="form-group">
            <label class="form-label" for="login">Tên đăng nhập hoặc email</label>
            <input class="form-input @error('login') is-invalid @enderror" id="login" name="login"
                   value="{{ old('login') }}" required autofocus autocomplete="username">
        </div>
        <div class="form-group">
            <label class="form-label" for="password">Mật khẩu</label>
            <input class="form-input @error('password') is-invalid @enderror" id="password" name="password"
                   type="password" required autocomplete="current-password">
        </div>
        <div class="flex-between mb-3">
            <label class="text-sm flex gap-1" style="align-items:center;">
                <input type="checkbox" name="remember" value="1"> Ghi nhớ đăng nhập
            </label>
            <a href="{{ route('password.request') }}" class="text-sm link-underline">Quên mật khẩu?</a>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Đăng nhập</button>
    </form>

    <p class="text-center text-sm mt-3 mb-0">
        Chưa có tài khoản? <a href="{{ route('register') }}" class="link-underline">Đăng ký ngay</a>
    </p>
@endsection
