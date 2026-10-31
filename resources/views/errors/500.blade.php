@extends('layouts.landing')
@section('title', 'Có lỗi xảy ra — NhoSub')
@section('content')
    <section class="section" style="min-height:60vh;display:flex;align-items:center;">
        <div class="container text-center">
            <h1 style="color:var(--danger);">500</h1>
            <p class="text-muted">Có lỗi xảy ra, chúng tôi đang khắc phục. Vui lòng thử lại sau ít phút.</p>
            <a href="{{ url('/') }}" class="btn btn-primary mt-2">Về trang chủ</a>
        </div>
    </section>
@endsection
