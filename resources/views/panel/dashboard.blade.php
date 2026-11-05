@extends('layouts.panel')
@section('title', 'Tổng quan')
@section('content')
    {{-- ======== HERO CHÀO MỪNG — khối tối duy nhất, minh hoạ SVG riêng ======== --}}
    <section class="dash-hero dot-pattern">
        <div class="dash-hero-text">
            <p class="dash-hero-eyebrow">{{ now()->translatedFormat('l, d/m/Y') }}</p>
            <h2 class="dash-hero-title">Chào {{ $user->username }} 👋</h2>
            <p class="dash-hero-sub">
                @if (($stats['running_orders'] ?? 0) > 0)
                    Bạn có <strong>{{ $stats['running_orders'] }}</strong> đơn đang chạy.
                    Theo dõi tiến độ ở danh sách đơn bên dưới.
                @elseif ($stats['total_orders'] === 0)
                    Bắt đầu bằng việc chọn một dịch vụ — đơn đầu tiên chỉ mất vài giây.
                @else
                    Hệ thống đang tự động cập nhật tiến độ đơn của bạn mỗi 5 phút.
                @endif
            </p>
            <div class="dash-hero-actions">
                @unless (auth()->user()->isAdmin())
                    <a href="{{ route('panel.orders.create') }}" class="btn btn-light">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                        Đặt đơn mới
                    </a>
                    <a href="{{ route('panel.deposit.create') }}" class="btn btn-hero-ghost">Nạp tiền</a>
                @else
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-light">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z"/></svg>
                        Vào trang quản trị
                    </a>
                    @if (Route::has('posts.create'))
                        <a href="{{ route('posts.create') }}" class="btn btn-hero-ghost">Tạo thông báo</a>
                    @endif
                @endunless
            </div>
        </div>

        {{-- Minh hoạ: nhịp tăng tương tác — cột mọc lên + điểm tim, vẽ SVG phẳng --}}
        <div class="dash-hero-art" aria-hidden="true">
            <svg viewBox="0 0 300 190" fill="none">
                <!-- lưới nền -->
                <g stroke="rgba(255,255,255,.10)" stroke-width="1">
                    <path d="M20 30h260M20 70h260M20 110h260M20 150h260"/>
                </g>
                <!-- cột mọc: 6 cột tăng dần, xanh sáng trên nền tối -->
                <g fill="rgba(255,255,255,.16)">
                    <rect x="30"  y="118" width="22" height="32" rx="4"/>
                    <rect x="72"  y="104" width="22" height="46" rx="4"/>
                    <rect x="114" y="112" width="22" height="38" rx="4"/>
                    <rect x="156" y="88"  width="22" height="62" rx="4"/>
                </g>
                <g fill="#5B9BF0">
                    <rect x="198" y="66"  width="22" height="84" rx="4"/>
                    <rect x="240" y="38"  width="22" height="112" rx="4"/>
                </g>
                <!-- đường nối đỉnh -->
                <path d="M41 112 L83 98 L125 106 L167 82 L209 60 L251 32"
                      stroke="#B9D3F5" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="251" cy="32" r="5" fill="#B9D3F5"/>
                <circle cx="251" cy="32" r="10" fill="none" stroke="#B9D3F5" stroke-opacity=".4" stroke-width="2"/>
                <!-- tim nổi phía trên cột cuối -->
                <g transform="translate(238 6)">
                    <path d="M7 12.2C3.6 9.4 1.5 7 1.5 4.6 1.5 2.7 3 1.2 4.9 1.2c1.2 0 2.3.6 2.9 1.6h.4c.6-1 1.7-1.6 2.9-1.6 1.9 0 3.4 1.5 3.4 3.4 0 2.4-2.1 4.8-5.5 7.6L7 12.2Z"
                          fill="#FF7A9C" stroke="none" transform="scale(1.6)"/>
                </g>
                <!-- mũi tên tăng ở góc -->
                <g transform="translate(24 14)">
                    <path d="M2 10 8 4l3 3 5-5" stroke="#7EE2A8" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M12.5 2H16v3.5" stroke="#7EE2A8" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                </g>
            </svg>
        </div>
    </section>

    {{-- ======== 4 THẺ SỐ LIỆU ======== --}}
    <div class="grid grid-4 mb-3">
        <div class="stat card-hover">
            <div class="stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 7a2 2 0 0 1 2-2h13a1 1 0 0 1 1 1v3"/><path d="M3 7v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H5"/><circle cx="17" cy="14" r="1"/></svg>
            </div>
            <div class="stat-body">
                <p class="stat-label">Số dư ví</p>
                <div class="stat-value primary">{{ number_format($stats['balance'], 0, ',', '.') }} ₫</div>
                <a href="{{ route('panel.deposit.create') }}" class="stat-link">+ Nạp thêm</a>
            </div>
        </div>
        <div class="stat card-hover">
            <div class="stat-icon success">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0l4-4m-4 4l-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
            </div>
            <div class="stat-body">
                <p class="stat-label">Tổng đã nạp</p>
                <div class="stat-value success">{{ number_format($stats['total_deposited'], 0, ',', '.') }} ₫</div>
                <a href="{{ route('panel.wallet.index') }}" class="stat-link">Lịch sử giao dịch</a>
            </div>
        </div>
        <div class="stat card-hover">
            <div class="stat-icon warning">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21V3m0 0l-4 4m4-4l4 4M20 17v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2"/></svg>
            </div>
            <div class="stat-body">
                <p class="stat-label">Đã dùng cho đơn</p>
                <div class="stat-value warning">{{ number_format($stats['total_spent'], 0, ',', '.') }} ₫</div>
                <span class="stat-link stat-link-plain">{{ number_format($stats['total_orders'], 0, ',', '.') }} đơn từ trước</span>
            </div>
        </div>
        <div class="stat card-hover">
            <div class="stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M13 2 4.5 13.5H11L9.5 22 19 9.5h-6.5L13 2Z"/></svg>
            </div>
            <div class="stat-body">
                <p class="stat-label">Đơn đang chạy</p>
                <div class="stat-value">{{ number_format($stats['running_orders'], 0, ',', '.') }}</div>
                <a href="{{ route('panel.orders.index') }}" class="stat-link">Xem danh sách đơn</a>
            </div>
        </div>
    </div>

    {{-- ======== THÔNG BÁO + THẺ HỒ SƠ ======== --}}
    <div class="dash-main-grid">
        <div class="card">
            <div class="flex-between mb-2">
                <h3 class="card-title mb-0">Thông báo</h3>
                @if (($latestPosts ?? collect())->isNotEmpty() || ($notifications ?? collect())->isNotEmpty())
                    <a href="{{ route('panel.notifications.index') }}" class="text-sm link-underline">Xem tất cả</a>
                @endif
            </div>

            {{-- Bài viết thông báo của admin --}}
            @forelse (($latestPosts ?? collect()) as $post)
                <a class="dash-notify" href="{{ route('content.post', $post->slug) }}">
                    <span class="dash-notify-icon dash-notify-admin">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                    </span>
                    <span class="dash-notify-body">
                        <span class="dash-notify-title">{{ $post->is_pinned ? '📌 ' : '' }}{{ $post->title }}</span>
                        <span class="dash-notify-meta">{{ $post->is_pinned ? 'Thông báo ghim · ' : 'Thông báo từ NhoSub · ' }}{{ $post->published_at?->format('d/m/Y') }}</span>
                    </span>
                </a>
            @empty
                {{-- Không có bài viết nào — khối thông báo hệ thống bên dưới vẫn hiện --}}
            @endforelse

            {{-- Thông báo hệ thống (đơn hoàn tất, hoàn tiền...) --}}
            @forelse ($notifications ?? collect() as $n)
                <div class="dash-notify">
                    <span class="dash-notify-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 6 9 17l-5-5"/></svg>
                    </span>
                    <span class="dash-notify-body">
                        <span class="dash-notify-title">{{ $n->data['title'] ?? 'Thông báo' }}</span>
                        <span class="dash-notify-meta">{{ $n->data['message'] ?? '' }}</span>
                        <span class="dash-notify-meta">{{ $n->created_at->diffForHumans() }}</span>
                    </span>
                </div>
            @empty
                @if (($latestPosts ?? collect())->isEmpty())
                    <div class="empty-block">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                        <div class="empty-title">Chưa có thông báo</div>
                        <p>Tin mới từ NhoSub và cập nhật đơn sẽ hiện tại đây.</p>
                    </div>
                @endif
            @endforelse
        </div>

        {{-- Thẻ hồ sơ mini --}}
        <aside class="dash-profile">
            <span class="dash-profile-avatar">{{ mb_strtoupper(mb_substr($user->username, 0, 1)) }}</span>
            <div class="dash-profile-name">{{ $user->username }}</div>
            <span class="dash-profile-badge">Khách hàng</span>
            <div class="dash-profile-row">
                <span>Số dư</span>
                <strong>{{ number_format($stats['balance'], 0, ',', '.') }} ₫</strong>
            </div>
            <div class="dash-profile-row">
                <span>Đơn đã đặt</span>
                <strong>{{ number_format($stats['total_orders'], 0, ',', '.') }}</strong>
            </div>
            <a href="{{ route('panel.profile.edit') }}" class="btn btn-outline btn-block">Hồ sơ của tôi</a>
        </aside>
    </div>

    {{-- ======== ĐƠN GẦN ĐÂY ======== --}}
    <div class="card mt-3">
        <div class="flex-between mb-2">
            <h3 class="card-title mb-0">Đơn gần đây</h3>
            <a href="{{ route('panel.orders.create') }}" class="btn btn-outline btn-sm">Đặt đơn mới</a>
        </div>

        @forelse (($recentOrders ?? collect()) as $order)
            <div class="dash-order-row">
                <a href="{{ route('panel.orders.show', $order) }}" class="dash-order-id link-underline">#{{ $order->id }}</a>
                <span class="dash-order-service truncate">{{ $order->service->name ?? '—' }}</span>
                <span class="dash-order-right">
                    @include('partials.order-status', ['status' => $order->status])
                    <span class="dash-order-amount">{{ number_format($order->total_amount, 0, ',', '.') }} ₫</span>
                </span>
            </div>
        @empty
            <div class="empty-block">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2"/></svg>
                <div class="empty-title">Chưa có đơn nào</div>
                <p>Chọn một dịch vụ trong mục Sản phẩm &amp; dịch vụ để bắt đầu.</p>
                <a href="{{ route('panel.orders.create') }}" class="btn btn-primary mt-1">Đặt đơn đầu tiên</a>
            </div>
        @endforelse
    </div>
@endsection
