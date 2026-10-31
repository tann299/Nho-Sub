@extends('layouts.landing')
@section('title', 'Không tìm thấy trang — NhoSub')
@section('content')
    <section class="section" style="min-height:60vh;display:flex;align-items:center;">
        <div class="container text-center">
            <h1 style="color:var(--primary);">404</h1>
            <p class="text-muted">Trang bạn tìm không tồn tại hoặc đã bị di chuyển.</p>
            <a href="{{ url('/') }}" class="btn btn-primary mt-2">Về trang chủ</a>
        </div>
    </section>
@endsection
