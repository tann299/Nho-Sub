@extends('layouts.panel')
@section('title', 'Danh sách dịch vụ')
@section('content')
    <div class="page-hero">
        <div class="page-hero-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h10"/></svg>
        </div>
        <div class="page-hero-body">
            <h2 class="page-hero-title">Danh sách dịch vụ</h2>
            <p class="page-hero-desc">Giá hiển thị cho 1.000 lượt. Chọn nền tảng ở menu bên trái để lọc nhanh.</p>
        </div>
        <a href="{{ route('panel.orders.create') }}" class="btn btn-primary page-hero-action">Đặt đơn mới</a>
    </div>

    <form method="GET" action="{{ route('panel.services.index') }}" class="card mb-3">
        <div class="grid grid-3" style="align-items:end;">
            <div class="form-group mb-0">
                <label class="form-label" for="q">Tìm dịch vụ</label>
                <input class="form-input" id="q" name="q" value="{{ $q }}" placeholder="Ví dụ: like, view, follow…">
            </div>
            <div class="form-group mb-0">
                <label class="form-label" for="platform">Nền tảng</label>
                <select class="form-select" id="platform" name="platform">
                    <option value="">Tất cả nền tảng</option>
                    @foreach ($platforms as $p)
                        <option value="{{ $p }}" @selected($platform === $p)>{{ ucfirst($p) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group mb-0">
                <button type="submit" class="btn btn-primary btn-block">Lọc dịch vụ</button>
            </div>
        </div>
    </form>

    @forelse ($categories as $category)
        <section class="mb-4">
            <div class="flex-between mb-2">
                <h2 style="font-size:17px;margin:0;">{{ $category->name }}</h2>
                <span class="badge badge-neutral">{{ ucfirst($category->platform) }}</span>
            </div>

            <div class="grid grid-2">
                @foreach ($category->services as $service)
                    <article class="card card-hover" style="display:flex;flex-direction:column;">
                        <h3 style="font-size:14.5px;margin-bottom:6px;">{{ $service->name }}</h3>

                        @if ($service->description)
                            <p class="text-sm text-muted" style="margin-bottom:10px;flex:1;">{{ $service->description }}</p>
                        @else
                            <div style="flex:1;"></div>
                        @endif

                        <div class="flex-between" style="background:var(--surface-tint);padding:8px 12px;border-radius:var(--radius-sm);margin-bottom:10px;">
                            <span class="text-sm text-muted">Giá / 1.000</span>
                            <strong style="color:var(--primary-dark);font-size:16px;">
                                {{ number_format($service->sell_price, 0, ',', '.') }} ₫
                            </strong>
                        </div>

                        <div class="flex-between text-sm text-muted" style="padding-bottom:8px;">
                            <span>{{ number_format($service->min_qty, 0, ',', '.') }} – {{ number_format($service->max_qty, 0, ',', '.') }} lượt</span>
                            <span>{{ $service->speed ?? '—' }}</span>
                        </div>

                        <div class="flex-between text-sm text-muted mb-2">
                            <span>Bắt đầu: {{ $service->avg_start_time ?? '—' }}</span>
                        </div>

                        <a href="{{ route('panel.orders.create', ['service_id' => $service->id]) }}"
                           class="btn btn-outline btn-block">Đặt đơn</a>
                    </article>
                @endforeach
            </div>
        </section>
    @empty
        <div class="empty-block">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h10"/></svg>
            <div class="empty-title">Không có dịch vụ nào khớp bộ lọc</div>
            <p>Thử từ khoá khác hoặc chọn nền tảng khác ở menu bên trái.</p>
            <a href="{{ route('panel.services.index') }}" class="btn btn-outline">Xoá bộ lọc</a>
        </div>
    @endforelse
@endsection
