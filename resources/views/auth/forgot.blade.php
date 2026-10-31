@extends('layouts.auth')
@section('title', 'Quên mật khẩu')
@section('content')
    <h1 class="auth-title">Quên mật khẩu</h1>
    <p class="auth-sub">Nhập email đã đăng ký, chúng tôi sẽ gửi liên kết đặt lại mật khẩu.</p>

    @unless ($mailConfigured)
        <div class="alert alert-warning">
            Hệ thống chưa cấu hình gửi email. Vui lòng liên hệ quản trị viên để được đặt lại mật khẩu.
        </div>
    @endunless

    <form method="POST" action="{{ route('password.email') }}" data-guard>
        @csrf
        <div class="form-group">
            <label class="form-label" for="email">Email</label>
            <input class="form-input @error('email') is-invalid @enderror" id="email" name="email"
                   type="email" value="{{ old('email') }}" required autofocus>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Gửi liên kết</button>
    </form>

    <p class="text-center text-sm mt-3 mb-0">
        <a href="{{ route('login') }}" class="link-underline">Quay lại đăng nhập</a>
    </p>
@endsection
