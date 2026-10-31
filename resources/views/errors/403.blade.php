@extends('layouts.landing')
@section('title', 'Không có quyền truy cập — NhoSub')
@section('content')
    <section class="section" style="min-height:60vh;display:flex;align-items:center;">
        <div class="container text-center">
            <h1 style="color:var(--danger);">403</h1>
            <p class="text-muted">Bạn không có quyền truy cập trang này.</p>
            <a href="{{ url('/') }}" class="btn btn-primary mt-2">Về trang chủ</a>
        </div>
    </section>
@endsection
