@extends('layouts.panel')
@section('title', 'Xác nhận đơn hàng')
@section('content')
    <div class="card" style="max-width:680px;">
        <h2 class="card-title">Xem lại đơn hàng</h2>

        <div class="table-wrap mb-3">
            <table class="table">
                <tbody>
                    <tr><th style="width:40%;">Dịch vụ</th><td>{{ $preview['service']->name }}</td></tr>
                    <tr><th>Link</th><td class="truncate" title="{{ $targetLink }}">{{ $targetLink }}</td></tr>
                    <tr><th>Số lượng</th><td>{{ number_format($preview['quantity'], 0, ',', '.') }}</td></tr>
                    <tr><th>Giá / 1.000</th><td>{{ number_format($preview['unit_price'], 0, ',', '.') }} ₫</td></tr>
                    <tr><th>Tổng tiền</th><td><strong style="color:var(--primary);font-size:18px;">{{ number_format($preview['total'], 0, ',', '.') }} ₫</strong></td></tr>
                    <tr><th>Số dư hiện tại</th><td>{{ number_format($preview['balance'], 0, ',', '.') }} ₫</td></tr>
                    <tr><th>Số dư sau khi đặt</th>
                        <td class="{{ $preview['balance_after'] < 0 ? 'text-danger' : 'text-success' }}">
                            {{ number_format($preview['balance_after'], 0, ',', '.') }} ₫
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if ($preview['balance_after'] < 0)
            <div class="alert alert-error">
                Số dư không đủ. Vui lòng <a href="{{ route('panel.deposit.create') }}">nạp thêm tiền</a>.
            </div>
        @endif

        <form method="POST" action="{{ route('panel.orders.store') }}" data-guard>
            @csrf
            <input type="hidden" name="service_id" value="{{ $preview['service']->id }}">
            <input type="hidden" name="target_link" value="{{ $targetLink }}">
            <input type="hidden" name="quantity" value="{{ $preview['quantity'] }}">
            <input type="hidden" name="client_token" value="{{ $clientToken }}">

            <div class="flex gap-1 flex-wrap">
                <button type="submit" class="btn btn-primary" @disabled($preview['balance_after'] < 0)>
                    Xác nhận đặt đơn
                </button>
                <a href="{{ route('panel.orders.create', ['service_id' => $preview['service']->id]) }}"
                   class="btn btn-ghost">Quay lại chỉnh sửa</a>
            </div>
        </form>
    </div>
@endsection
