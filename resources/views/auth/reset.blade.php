@extends('layouts.auth')
@section('title', 'Đặt lại mật khẩu')
@section('content')
    <h1 class="auth-title">Đặt lại mật khẩu</h1>
    <p class="auth-sub">Chọn mật khẩu mới cho tài khoản của bạn.</p>

    <form method="POST" action="{{ route('password.store') }}" data-guard>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="form-group">
            <label class="form-label" for="email">Email</label>
            <input class="form-input @error('email') is-invalid @enderror" id="email" name="email"
                   type="email" value="{{ old('email', $email) }}" required>
        </div>
        <div class="form-group">
            <label class="form-label" for="password">Mật khẩu mới</label>
            <input class="form-input @error('password') is-invalid @enderror" id="password" name="password"
                   type="password" required autocomplete="new-password">
        </div>
        <div class="form-group">
            <label class="form-label" for="password_confirmation">Nhập lại mật khẩu mới</label>
            <input class="form-input" id="password_confirmation" name="password_confirmation"
                   type="password" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Đặt lại mật khẩu</button>
    </form>
@endsection
