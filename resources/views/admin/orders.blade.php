@extends('layouts.panel')
@section('title', 'Quản lý đơn hàng')
@section('content')
    <div class="page-hero">
        <div class="page-hero-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/></svg>
        </div>
        <div class="page-hero-body">
            <h2 class="page-hero-title">Tất cả đơn hàng</h2>
            <p class="page-hero-desc">Tra cứu theo mã, khách, trạng thái. Đơn kẹt chờ xử lý có cảnh báo riêng.</p>
        </div>
    </div>

    <form method="GET" class="card mb-3">
        <div class="grid grid-4" style="align-items:end;">
            <div class="form-group mb-0">
                <label class="form-label" for="q">Mã đơn / link</label>
                <input class="form-input" id="q" name="q" value="{{ $q }}">
            </div>
            <div class="form-group mb-0">
                <label class="form-label" for="username">Khách hàng</label>
                <input class="form-input" id="username" name="username" value="{{ $username }}">
            </div>
            <div class="form-group mb-0">
                <label class="form-label" for="status">Trạng thái</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tất cả</option>
                    @foreach (['pending','charged','processing','completed','partial','canceled','error','stuck_review'] as $s)
                        <option value="{{ $s }}" @selected($status === $s)>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group mb-0"><button class="btn btn-primary btn-block">Lọc</button></div>
        </div>
    </form>

    <div class="card" style="padding:0;overflow:hidden;">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Mã</th><th>Khách</th><th>Dịch vụ</th><th class="num">SL</th>
                        <th class="num">Thu</th><th class="num">Chi</th><th>Trạng thái</th><th>Ngày</th><th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($orders as $o)
                    <tr>
                        <td><strong>#{{ $o->id }}</strong></td>
                        <td class="text-sm">{{ $o->user->username ?? '—' }}</td>
                        <td class="truncate text-sm">{{ $o->service->name ?? '—' }}</td>
                        <td class="num">{{ number_format($o->quantity, 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($o->total_amount, 0, ',', '.') }}</td>
                        <td class="num text-muted">{{ number_format($o->cost_amount, 0, ',', '.') }}</td>
                        <td>@include('partials.order-status', ['status' => $o->status])</td>
                        <td class="text-sm text-muted nowrap">{{ $o->created_at->format('d/m H:i') }}</td>
                        <td><a class="btn btn-ghost btn-sm" href="{{ route('admin.orders.show', $o) }}">Xem</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $orders->links() }}</div>
@endsection
