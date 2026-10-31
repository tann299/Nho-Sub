@extends('layouts.landing')

@section('content')
    <section class="hero">
        <div class="container">
            <div class="hero-grid">
                <div>
                    <span class="hero-badge">Panel tăng tương tác cho người Việt</span>
                    <h1>Tăng tương tác mạng xã hội,<br>giá minh bạch theo từng 1.000 lượt</h1>
                    <p class="hero-lead">
                        Sub nhỏ, lớn dần cùng bạn. Like, follow, view, comment, share cho
                        Facebook, TikTok, Instagram và YouTube.
                    </p>
                    <div class="hero-actions">
                        <a href="{{ route('register') }}" class="btn btn-primary">Đăng ký miễn phí</a>
                        <a href="{{ route('login') }}" class="btn btn-outline">Đăng nhập</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
