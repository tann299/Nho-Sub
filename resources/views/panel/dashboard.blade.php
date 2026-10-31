@extends('layouts.panel')
@section('title', 'Tổng quan')
@section('content')
    <div class="card">
        <h2 class="card-title">Xin chào, {{ $user->username }}!</h2>
        <p class="text-muted mb-0">Số dư hiện tại: <strong>{{ number_format($user->balance, 0, ',', '.') }} ₫</strong></p>
    </div>
@endsection
