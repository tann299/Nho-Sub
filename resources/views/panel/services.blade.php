@extends('layouts.panel')
@section('title', 'Bảng giá dịch vụ')
@section('content')
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
                <h2 style="font-size:19px;margin:0;">{{ $category->name }}</h2>
                <span class="badge badge-neutral">{{ ucfirst($category->platform) }}</span>
            </div>

            <div class="grid grid-2">
                @foreach ($category->services as $service)
                    <article class="card card-hover">
                        <h3 style="font-size:16px;margin-bottom:8px;">{{ $service->name }}</h3>

                        @if ($service->description)
                            <p class="text-sm text-muted" style="margin-bottom:12px;">{{ $service->description }}</p>
                        @endif

                        <div class="flex-between" style="padding:10px 0;border-top:1px solid #EDF2FA;">
                            <span class="text-sm text-muted">Giá / 1.000</span>
                            <strong style="color:var(--primary);font-size:17px;">
                                {{ number_format($service->sell_price, 0, ',', '.') }} ₫
                            </strong>
                        </div>

                        <div class="flex-between text-sm text-muted" style="padding-bottom:10px;">
                            <span>Số lượng: {{ number_format($service->min_qty, 0, ',', '.') }} – {{ number_format($service->max_qty, 0, ',', '.') }}</span>
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
        <div class="card empty-state">
            <p>Danh mục đang cập nhật. Vui lòng quay lại sau.</p>
        </div>
    @endforelse
@endsection
