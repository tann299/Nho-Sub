@php
    // Menu người dùng — gọn nhất có thể:
    // 1. Tổng quan (dashboard, đổi tên từ "Bảng điều khiển")
    // 2. Sản phẩm & dịch vụ (accordion nền tảng + app)
    // 3. Tài khoản (đơn của tôi, nạp tiền, hồ sơ, hỗ trợ — gộp 4 nhóm cũ)
    // 4. Quản trị (admin)

    $topGroups = [
        'Tổng quan' => [
            ['panel.dashboard', 'Tổng quan', 'M3 12l9-9 9 9M5 10v10h14V10'],
        ],
    ];

    $midGroups = [
        'Tài khoản' => [
            ['panel.orders.index', 'Đơn hàng của tôi', 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2'],
            ['panel.deposit.create', 'Nạp tiền', 'M12 3v12m0 0l4-4m-4 4l-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2'],
            ['panel.profile.edit', 'Hồ sơ cá nhân', 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z'],
            ['panel.support.index', 'Trung tâm hỗ trợ', 'M9.1 9a3 3 0 1 1 4.6 2.5c-.9.6-1.7 1.3-1.7 2.5M12 17h.01'],
        ],
    ];

    $adminGroups = [
        'Quản trị' => [
            ['admin.dashboard', 'Tổng quan', 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z'],
            ['admin.orders.index', 'Đơn hàng', 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2'],
            ['admin.deposits.index', 'Nạp tiền', 'M12 3v12m0 0l4-4m-4 4l-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2'],
            ['admin.users.index', 'Người dùng', 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75'],
            ['admin.services.index', 'Dịch vụ', 'M20 7l-8-4-8 4 8 4 8-4zM4 12l8 4 8-4M4 17l8 4 8-4'],
            ['admin.settings.edit', 'Cấu hình', 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6 1.65 1.65 0 0 0 10 3.09V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.14.6.6 1.05 1.2 1.2H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z'],
        ],
        // Gỡ khỏi menu chính, truy cập trực tiếp bằng URL (vẫn hoạt động):
        // - admin.posts.index    → /admin/posts     (bài viết thông báo)
        // - admin.webhooks.index → /admin/webhooks  (nhật ký webhook)
        // - admin.apiLogs.index  → /admin/api-logs  (nhật ký API)
        // Người dùng (bỏ khỏi menu, URL vẫn chạy):
        // - panel.services.index → /services         (đặt đơn — vào từ accordion hoặc dashboard)
        // - panel.orders.create  → /orders/create
        // - panel.wallet.index   → /wallet
        // - panel.profile.password → /settings/password
    ];

    $platformLabels = [
        'facebook' => 'Facebook',
        'tiktok' => 'TikTok',
        'instagram' => 'Instagram',
        'youtube' => 'YouTube',
        'khac' => 'Khác',
    ];

    $byPlatform = isset($menuCategories)
        ? $menuCategories->groupBy('platform')
        : collect();
@endphp

{{-- 0. Quản trị (chỉ admin) — ĐẦU TIÊN theo yêu cầu --}}
@auth
    @if (auth()->user()->isAdmin())
        @php
            $adminAvailable = array_values(array_filter(
                $adminGroups['Quản trị'],
                fn ($i) => Route::has($i[0])
            ));
        @endphp
        @if (! empty($adminAvailable))
            <div class="nav-group">
                <div class="nav-group-title">Quản trị</div>
                @foreach ($adminAvailable as [$routeName, $label, $icon])
                    <a href="{{ route($routeName) }}"
                       class="nav-item {{ request()->routeIs($routeName) ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
                        <span>{{ $label }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    @endif
@endauth

{{-- 1. Tổng quan & Dịch vụ --}}
@foreach ($topGroups as $groupName => $items)
    @php
        $available = array_values(array_filter($items, fn ($i) => Route::has($i[0])));
    @endphp
    @if (! empty($available))
        <div class="nav-group">
            <div class="nav-group-title">{{ $groupName }}</div>
            @foreach ($available as [$routeName, $label, $icon])
                <a href="{{ route($routeName) }}"
                   class="nav-item {{ request()->routeIs($routeName) ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
                    <span>{{ $label }}</span>
                </a>
            @endforeach
        </div>
    @endif
@endforeach

{{-- 2. Sản phẩm & dịch vụ: nền tảng accordion → danh mục con (đặt ngay sau Dịch vụ) --}}
@if ($byPlatform->isNotEmpty())
    <div class="nav-group">
        <div class="nav-group-title">{{ __('Sản phẩm & dịch vụ') }}</div>
        @foreach ($byPlatform as $platform => $categories)
            @php
                $platformUrl = route('panel.services.index', ['platform' => $platform]);
                $isPlatformActive = request()->routeIs('panel.services.index')
                    && request()->query('platform') === $platform;

                $activeCatId = (int) request()->query('category_id', 0);
                $hasActiveCat = request()->routeIs('panel.services.index')
                    && $activeCatId > 0
                    && $categories->contains('id', $activeCatId);
            @endphp

            @if ($platform === 'khac')
                {{-- Nền tảng "Khác": mỗi app một accordion riêng với logo app --}}
                @php
                    $byApp = $categories->groupBy(fn ($c) => $c->app !== '' ? $c->app : 'khac');
                    $appLabels = [
                        'twitter' => 'X (Twitter)', 'shopee' => 'Shopee', 'threads' => 'Threads',
                        'kwai' => 'Kwai', 'soundcloud' => 'SoundCloud', 'traffic' => 'Traffic Web',
                        'khac' => 'Khác',
                    ];
                @endphp
                @foreach ($byApp as $app => $appCategories)
                    @php
                        $logoFile = in_array($app, ['twitter', 'shopee', 'threads', 'kwai', 'soundcloud', 'traffic'], true)
                            ? $app : 'khac';
                        $appLabel = $appLabels[$app] ?? ucfirst($app);
                        $appActive = request()->routeIs('panel.services.index')
                            && $activeCatId > 0
                            && $appCategories->contains('id', $activeCatId);
                    @endphp
                    <details class="nav-acc" @if ($appActive) open @endif>
                        <summary class="nav-item nav-acc-summary {{ $appActive ? 'active' : '' }}">
                            <img src="{{ asset('assets/img/platform-' . $logoFile . '.svg') }}"
                                 class="platform-logo" data-platform="{{ $app }}"
                                 alt="{{ $appLabel }}" width="18" height="18" loading="lazy">
                            <span>{{ $appLabel }}</span>
                            <svg class="nav-acc-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M6 9l6 6 6-6"/>
                            </svg>
                        </summary>
                        <div class="nav-acc-body">
                            @foreach ($appCategories as $category)
                                @php
                                    $catUrl = route('panel.services.index', ['category_id' => $category->id]);
                                    $isCatActive = request()->routeIs('panel.services.index')
                                        && (int) request()->query('category_id') === (int) $category->id;
                                @endphp
                                <a href="{{ $catUrl }}" class="nav-item nav-item-sub {{ $isCatActive ? 'active' : '' }}">
                                    <span>{{ $category->name }}</span>
                                </a>
                            @endforeach
                        </div>
                    </details>
                @endforeach
            @else
                {{-- Nền tảng chính: 1 accordion + logo nền tảng --}}
                <details class="nav-acc" @if ($hasActiveCat) open @endif>
                    <summary class="nav-item nav-acc-summary {{ $isPlatformActive ? 'active' : '' }}">
                        <img src="{{ asset('assets/img/platform-' . $platform . '.svg') }}"
                             class="platform-logo" data-platform="{{ $platform }}"
                             alt="{{ $platformLabels[$platform] ?? $platform }}" width="18" height="18" loading="lazy">
                        <span>{{ $platformLabels[$platform] ?? ucfirst($platform) }}</span>
                        <svg class="nav-acc-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M6 9l6 6 6-6"/>
                        </svg>
                    </summary>

                    <div class="nav-acc-body">
                        <a href="{{ $platformUrl }}"
                           class="nav-item nav-item-sub {{ $isPlatformActive ? 'active' : '' }}">
                            <span>Tất cả {{ $platformLabels[$platform] ?? ucfirst($platform) }}</span>
                        </a>
                        @foreach ($categories as $category)
                            @php
                                $catUrl = route('panel.services.index', ['category_id' => $category->id]);
                                $isCatActive = request()->routeIs('panel.services.index')
                                    && (int) request()->query('category_id') === (int) $category->id;
                            @endphp
                            <a href="{{ $catUrl }}" class="nav-item nav-item-sub {{ $isCatActive ? 'active' : '' }}">
                                <span>{{ $category->name }}</span>
                            </a>
                        @endforeach
                    </div>
                </details>
            @endif
        @endforeach
    </div>
@endif

{{-- 3. Đơn hàng, Thanh toán, Cài đặt --}}
@foreach ($midGroups as $groupName => $items)
    @php
        $available = array_values(array_filter($items, fn ($i) => Route::has($i[0])));
    @endphp
    @if (! empty($available))
        <div class="nav-group">
            <div class="nav-group-title">{{ $groupName }}</div>
            @foreach ($available as [$routeName, $label, $icon])
                <a href="{{ route($routeName) }}"
                   class="nav-item {{ request()->routeIs($routeName) ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
                    <span>{{ $label }}</span>
                </a>
            @endforeach
        </div>
    @endif
@endforeach
