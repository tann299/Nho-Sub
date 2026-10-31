@extends('layouts.panel')
@section('title', 'Hồ sơ cá nhân')
@section('content')
    <div class="card" style="max-width:560px;">
        <h2 class="card-title">Hồ sơ cá nhân</h2>

        <div class="alert alert-info">
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
@endsection
