{{-- Thanh điều hướng dưới cùng — chỉ hiện trên điện thoại --}}
<nav class="mobile-nav" aria-label="Điều hướng nhanh">
    @php
        $tabs = array_values(array_filter([
            Route::has('panel.dashboard')
                ? ['panel.dashboard', 'Tổng quan', 'M3 12l9-9 9 9M5 10v10h14V10', request()->routeIs('panel.dashboard')]
                : null,
            Route::has('panel.services.index')
                ? ['panel.services.index', 'Dịch vụ', 'M20 7l-8-4-8 4 8 4 8-4zM4 12l8 4 8-4M4 17l8 4 8-4', request()->routeIs('panel.services.*')]
                : null,
            Route::has('panel.orders.index')
                ? ['panel.orders.index', 'Đơn hàng', 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2', request()->routeIs('panel.orders.*')]
                : null,
            Route::has('panel.deposit.create')
                ? ['panel.deposit.create', 'Nạp tiền', 'M12 3v12m0 0l4-4m-4 4l-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2', request()->routeIs('panel.deposit.*', 'panel.wallet.*')]
                : null,
        ]));
    @endphp

    @foreach ($tabs as [$routeName, $label, $icon, $active])
        <a href="{{ route($routeName) }}" class="mobile-nav-item {{ $active ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
            <span>{{ $label }}</span>
        </a>
    @endforeach

    {{-- Nút mở menu đầy đủ (ngăn kéo bên trái) --}}
    <button type="button" class="mobile-nav-item" data-sidebar-toggle>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
        <span>Menu</span>
    </button>
</nav>
