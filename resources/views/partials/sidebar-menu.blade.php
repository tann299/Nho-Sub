@php
    // Menu theo 7 nhóm đúng yêu cầu. Mỗi mục: [route, nhãn, icon path].
    $groups = [
        'Tổng quan' => [
            ['panel.dashboard', 'Bảng điều khiển', 'M3 12l9-9 9 9M5 10v10h14V10'],
        ],
        'Dịch vụ' => [
            ['panel.services.index', 'Bảng giá dịch vụ', 'M4 6h16M4 12h16M4 18h10'],
        ],
        'Đặt đơn' => [
            ['panel.orders.create', 'Đặt đơn mới', 'M12 5v14M5 12h14'],
        ],
        'Đơn hàng' => [
            ['panel.orders.index', 'Đơn hàng của tôi', 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2'],
        ],
        'Nạp tiền' => [
            ['panel.deposit.create', 'Nạp tiền', 'M12 3v12m0 0l4-4m-4 4l-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2'],
            ['panel.wallet.index', 'Lịch sử giao dịch', 'M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z'],
        ],
        'Hỗ trợ' => [
            ['panel.support.index', 'Trung tâm hỗ trợ', 'M9.1 9a3 3 0 1 1 4.6 2.5c-.9.6-1.7 1.3-1.7 2.5M12 17h.01'],
        ],
        'Cài đặt' => [
            ['panel.profile.edit', 'Hồ sơ cá nhân', 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z'],
            ['panel.profile.password', 'Đổi mật khẩu', 'M7 11V7a5 5 0 0 1 10 0v4M5 11h14v10H5z'],
        ],
    ];

    $adminGroups = [
        'Quản trị' => [
            ['admin.dashboard', 'Tổng quan hệ thống', 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z'],
            ['admin.users.index', 'Người dùng', 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75'],
            ['admin.orders.index', 'Tất cả đơn hàng', 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2'],
            ['admin.services.index', 'Quản lý dịch vụ', 'M20 7l-8-4-8 4 8 4 8-4zM4 12l8 4 8-4M4 17l8 4 8-4'],
            ['admin.deposits.index', 'Duyệt nạp tiền', 'M12 3v12m0 0l4-4m-4 4l-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2'],
            ['admin.webhooks.index', 'Nhật ký webhook', 'M4 4h16v16H4zM8 8h8M8 12h8M8 16h5'],
            ['admin.apiLogs.index', 'Nhật ký API', 'M4 7h16M4 12h16M4 17h10'],
            ['admin.settings.edit', 'Cấu hình hệ thống', 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6 1.65 1.65 0 0 0 10 3.09V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.14.6.6 1.05 1.2 1.2H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z'],
        ],
    ];
@endphp

@foreach ($groups as $groupName => $items)
    @php
        // Chỉ render nhóm nếu có ít nhất một route đã tồn tại (tránh lỗi khi
        // một số trang chưa được triển khai ở giai đoạn hiện tại).
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
