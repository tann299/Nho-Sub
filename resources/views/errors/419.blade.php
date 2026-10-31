@extends('layouts.landing')
@section('title', 'Phiên làm việc hết hạn — NhoSub')
@section('content')
    <section class="section" style="min-height:60vh;display:flex;align-items:center;">
        <div class="container text-center">
            <h1 style="color:var(--warning);">419</h1>
            <p class="text-muted">Phiên làm việc đã hết hạn. Vui lòng tải lại trang và thử lại.</p>
            <a href="{{ url()->previous() }}" class="btn btn-primary mt-2">Quay lại</a>
        </div>
    </section>
@endsection
