@extends('layouts.panel')
@section('title', 'Đổi mật khẩu')
@section('content')
    <div class="card" style="max-width:560px;">
        <h2 class="card-title">Đổi mật khẩu</h2>
        <form method="POST" action="{{ route('panel.profile.password.update') }}" data-guard>
            @csrf
            <div class="form-group">
                <label class="form-label" for="current_password">Mật khẩu hiện tại</label>
                <input class="form-input @error('current_password') is-invalid @enderror"
                       id="current_password" name="current_password" type="password" required autocomplete="current-password">
            </div>
            <div class="form-group">
                <label class="form-label" for="password">Mật khẩu mới</label>
                <input class="form-input @error('password') is-invalid @enderror"
                       id="password" name="password" type="password" required autocomplete="new-password">
                <div class="form-hint">Tối thiểu 8 ký tự, khác mật khẩu hiện tại.</div>
            </div>
            <div class="form-group">
                <label class="form-label" for="password_confirmation">Nhập lại mật khẩu mới</label>
                <input class="form-input" id="password_confirmation" name="password_confirmation"
                       type="password" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary">Cập nhật mật khẩu</button>
        </form>
    </div>
@endsection
