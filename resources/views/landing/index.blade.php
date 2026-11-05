@extends('layouts.landing')

@section('content')
    {{-- HERO — nơi DUY NHẤT trong hệ thống được dùng gradient (cực nhẹ, cùng tông) --}}
    <section class="hero">
        <div class="container">
            <div class="hero-grid">
                <div>
                    <span class="hero-badge">Panel tăng tương tác cho người Việt</span>

                    <h1>Tăng tương tác mạng xã hội,<br>giá minh bạch theo từng 1.000 lượt</h1>

                    <p class="hero-lead">
                        Sub nhỏ, lớn dần cùng bạn. Like, follow, view, comment, share cho
                        Facebook, TikTok, Instagram và YouTube — đặt đơn trong 30 giây,
                        theo dõi trạng thái theo thời gian thực.
                    </p>

                    <div class="hero-actions">
                        @auth
                            <a href="{{ route('panel.dashboard') }}" class="btn btn-primary">Vào bảng điều khiển</a>
                        @else
                            <a href="{{ route('register') }}" class="btn btn-primary">Đăng ký ngay</a>
                            <a href="{{ route('login') }}" class="btn btn-outline">Đăng nhập</a>
                        @endauth
                    </div>

                    <div class="hero-stats">
                        <div class="hero-stat">
                            <strong>5+</strong>
                            <span>Nền tảng hỗ trợ</span>
                        </div>
                        <div class="hero-stat">
                            <strong>24/7</strong>
                            <span>Đơn chạy tự động</span>
                        </div>
                        <div class="hero-stat">
                            <strong>100%</strong>
                            <span>Hoàn tiền phần chưa chạy</span>
                        </div>
                    </div>
                </div>

                <div class="card" style="padding:0;overflow:hidden;">
                    <div style="padding:18px 20px;background:var(--primary);color:#fff;font-weight:700;">
                        Bảng giá tham khảo
                    </div>
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr><th>Dịch vụ</th><th class="num">Giá / 1.000</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($priceSamples as $s)
                                    <tr>
                                        <td class="text-sm">{{ $s->name }}</td>
                                        <td class="num"><strong>{{ number_format($s->sell_price, 0, ',', '.') }} ₫</strong></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="text-center text-muted">Đang cập nhật bảng giá.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- NỀN TẢNG — section trắng --}}
    <section class="section">
        <div class="container">
            <div class="section-head">
                <h2>Hỗ trợ các nền tảng phổ biến</h2>
                <p>Một tài khoản dùng cho tất cả dịch vụ bạn cần.</p>
            </div>

            <div class="grid grid-4">
                @foreach ([
                    ['Facebook', 'platform-facebook.svg', 'Like, follow, share, comment, member'],
                    ['TikTok', 'platform-tiktok.svg', 'View, like, follow, share'],
                    ['Instagram', 'platform-instagram.svg', 'Follow, like, view story'],
                    ['YouTube', 'platform-youtube.svg', 'Subscriber, view, like'],
                    ['X (Twitter)', 'platform-twitter.svg', 'Follow, retweet, like'],
                    ['Shopee', 'platform-shopee.svg', 'Follow, like, share gian hàng'],
                    ['Threads', 'platform-threads.svg', 'Follow, like bài viết'],
                    ['Kwai', 'platform-kwai.svg', 'View, like, follow'],
                    ['SoundCloud', 'platform-soundcloud.svg', 'Play, like, follow'],
                    ['Traffic Web', 'platform-traffic.svg', 'Traffic website, Alexa rank'],
                    ['Khác', 'platform-khac.svg', 'Dịch vụ đặc biệt theo yêu cầu'],
                ] as [$name, $logo, $desc])
                    <div class="card card-hover text-center platform-card">
                        <img class="platform-logo-lg" src="{{ asset('assets/img/' . $logo) }}" alt="{{ $name }}" loading="lazy">
                        <h3 style="font-size:16px;">{{ $name }}</h3>
                        <p class="text-sm text-muted mb-0">{{ $desc }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- TÍNH NĂNG — section nền xám nhạt --}}
    <section class="section section-alt dot-pattern" id="tinh-nang">
        <div class="container">
            <div class="section-head">
                <h2>Vì sao chọn NhoSub?</h2>
                <p>Làm đúng ngay từ đầu: giá rõ, tiền rõ, trạng thái rõ.</p>
            </div>

            <div class="grid grid-3">
                @foreach ([
                    ['Giá theo 1.000 lượt', 'Mọi dịch vụ hiển thị giá trên 1.000 lượt, tổng tiền làm tròn xuống. Không phí ẩn.'],
                    ['Tự động hoàn tiền', 'Đơn chạy một phần hoặc bị huỷ sẽ tự hoàn phần chưa chạy vào ví trong 5 phút.'],
                    ['Theo dõi minh bạch', 'Xem số lượng đã chạy, còn lại và lịch sử hoàn tiền của từng đơn.'],
                    ['Nạp tiền tự động', 'Chuyển khoản đúng nội dung là tiền vào ví trong vài giây, không cần chờ duyệt.'],
                    ['Hỗ trợ tiếng Việt', 'Đội ngũ hỗ trợ 8:00 – 22:00 hằng ngày, trả lời bằng tiếng Việt.'],
                    ['Bảo mật ví', 'Mọi giao dịch đều có mã chống trùng, số dư không bao giờ âm.'],
                ] as [$title, $desc])
                    <article class="card card-hover">
                        <h3 style="font-size:16px;">{{ $title }}</h3>
                        <p class="text-sm text-muted mb-0">{{ $desc }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- BẢNG GIÁ — section trắng --}}
    <section class="section" id="bang-gia">
        <div class="container">
            <div class="section-head">
                <h2>Bảng giá mẫu</h2>
                <p>Giá thực tế cập nhật liên tục trong bảng điều khiển.</p>
            </div>

            <div class="grid grid-3">
                @foreach ($pricingTiers as $tier)
                    <div class="card {{ $tier['highlight'] ? 'card-hover' : '' }}"
                         style="{{ $tier['highlight'] ? 'border-color:var(--primary);' : '' }}">
                        @if ($tier['highlight'])
                            <span class="badge badge-processing mb-2">Phổ biến nhất</span>
                        @endif
                        <h3 style="font-size:18px;">{{ $tier['name'] }}</h3>
                        <div style="font-size:26px;font-weight:800;color:var(--primary);margin:8px 0;">
                            {{ $tier['price'] }}
                        </div>
                        <p class="text-sm text-muted">{{ $tier['desc'] }}</p>
                        <ul class="text-sm" style="list-style:none;padding:0;margin:16px 0;color:var(--muted);">
                            @foreach ($tier['features'] as $f)
                                <li style="margin-bottom:8px;">✓ {{ $f }}</li>
                            @endforeach
                        </ul>
                        <a href="{{ route('register') }}" class="btn btn-primary btn-block">Bắt đầu ngay</a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ĐÁNH GIÁ — section xám --}}
    <section class="section section-alt" id="danh-gia">
        <div class="container">
            <div class="section-head">
                <h2>Khách hàng nói gì</h2>
                <p>Phản hồi mẫu từ khách hàng sử dụng panel.</p>
            </div>

            <div class="grid grid-3">
                @foreach ([
                    ['Nguyễn T. Hà', 'Chủ shop thời trang', 'Nạp tiền buổi sáng, 2 phút sau thấy vào ví. Đơn TikTok chạy nhanh, trạng thái cập nhật đều.'],
                    ['Trần Minh K.', 'Agency nhỏ', 'Đơn bị huỷ giữa chừng mà tiền phần chưa chạy được hoàn tự động, không phải nhắn hỏi admin.'],
                    ['Lê Quốc B.', 'Kinh doanh online', 'Giá hiển thị theo 1.000 lượt nên tính lời lỗ dễ. Giao diện dễ dùng cho người mới.'],
                ] as [$name, $role, $quote])
                    <article class="card card-hover">
                        <div style="color:var(--warning);margin-bottom:10px;letter-spacing:2px;">★★★★★</div>
                        <p style="font-size:15px;">“{{ $quote }}”</p>
                        <div class="text-sm">
                            <strong>{{ $name }}</strong>
                            <span class="text-muted"> · {{ $role }}</span>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- FAQ — section trắng --}}
    <section class="section" id="faq">
        <div class="container" style="max-width:820px;">
            <div class="section-head">
                <h2>Câu hỏi thường gặp</h2>
                <p>Chưa tìm thấy câu trả lời? Liên hệ hotro@nhosub.vn.</p>
            </div>

            @foreach ([
                ['Nạp tiền bao lâu thì vào ví?', 'Chuyển khoản đúng nội dung NHOSUB <TÊN ĐĂNG NHẬP> thường được cộng tự động trong vài giây. Nếu sau 15 phút chưa thấy, bạn gửi yêu cầu nạp ở trang Nạp tiền để admin kiểm tra.'],
                ['Đơn chạy một phần thì tính tiền thế nào?', 'Bạn chỉ trả cho phần đã chạy. Phần chưa chạy được hoàn lại ví tự động, tối đa trong 5 phút sau khi nhà cung cấp cập nhật trạng thái.'],
                ['Có mức nạp tối thiểu không?', 'Có. Mức tối thiểu mặc định là 10.000 ₫ cho mỗi giao dịch.'],
                ['Tôi có thể đổi giá bán hay bán lại cho người khác không?', 'Giai đoạn này panel dành cho khách lẻ. Tính năng đại lý cấp dưới sẽ có ở giai đoạn sau.'],
                ['Đơn của tôi bị lỗi thì sao?', 'Nếu nhà cung cấp không nhận đơn, hệ thống hoàn 100% tiền vào ví ngay và đánh dấu đơn lỗi. Bạn có thể đặt lại.'],
            ] as [$q, $a])
                <details class="faq-item">
                    <summary>{{ $q }}</summary>
                    <div class="faq-body">{{ $a }}</div>
                </details>
            @endforeach
        </div>
    </section>

    {{-- CTA cuối --}}
    <section class="section section-alt">
        <div class="container text-center">
            <h2>Bắt đầu từ một đơn nhỏ</h2>
            <p class="text-muted" style="max-width:560px;margin:0 auto 24px;">
                Nạp tiền khi cần. Không phí duy trì, không ràng buộc.
            </p>
            @guest
                <a href="{{ route('register') }}" class="btn btn-primary">Tạo tài khoản miễn phí</a>
            @else
                <a href="{{ route('panel.orders.create') }}" class="btn btn-primary">Đặt đơn ngay</a>
            @endguest
        </div>
    </section>
@endsection
