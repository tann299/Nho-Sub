@extends('layouts.panel')
@section('title', 'Quản lý dịch vụ')
@section('content')
    <div class="card mb-3">
        <div class="flex-between flex-wrap gap-2">
            <form method="GET" class="flex gap-1 flex-wrap" style="align-items:end;">
                <input class="form-input" name="q" value="{{ $q }}" placeholder="Tìm theo tên…" style="width:220px;">
                <select class="form-select" name="category_id" style="width:200px;">
                    <option value="0">Tất cả danh mục</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}" @selected($categoryId === $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
                <button class="btn btn-outline" type="submit">Lọc</button>
            </form>

            <form method="POST" action="{{ route('admin.services.sync') }}" data-guard>
                @csrf
                <button class="btn btn-primary" type="submit">Đồng bộ từ nhà cung cấp</button>
            </form>
        </div>
    </div>

    <div class="card" style="padding:0;overflow:hidden;">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID NCC</th><th>Tên dịch vụ</th><th class="num">Giá gốc</th>
                        <th class="num">Giá bán</th><th class="num">Min/Max</th><th>Bán</th><th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($services as $s)
                    <tr>
                        <td class="mono text-sm">{{ $s->provider_service_id }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.services.update', $s) }}" id="f-{{ $s->id }}">
                                @csrf
                                <input class="form-input" name="name" value="{{ $s->name }}" style="min-height:36px;padding:6px 10px;">
                                <div class="text-sm text-muted mt-1">{{ $s->category->name ?? '—' }}</div>
                            </form>
                        </td>
                        <td class="num">
                            {{ number_format($s->cost_price, 0, ',', '.') }}
                            <div class="text-sm text-muted">{{ $s->cost_usd }} USD</div>
                        </td>
                        <td class="num">
                            <input class="form-input" form="f-{{ $s->id }}" name="sell_price" type="number"
                                   value="{{ $s->sell_price }}" style="width:120px;min-height:36px;padding:6px 10px;text-align:right;">
                            @if ($s->sell_price_override)
                                <div class="text-sm" style="color:var(--primary);">Giá riêng</div>
                            @endif
                        </td>
                        <td class="num text-sm">{{ number_format($s->min_qty) }} / {{ number_format($s->max_qty) }}</td>
                        <td>
                            <label class="text-sm flex gap-1" style="align-items:center;">
                                <input type="hidden" form="f-{{ $s->id }}" name="is_active" value="0">
                                <input type="checkbox" form="f-{{ $s->id }}" name="is_active" value="1" @checked($s->is_active)>
                                Hiện
                            </label>
                        </td>
                        <td>
                            <button class="btn btn-outline btn-sm" type="submit" form="f-{{ $s->id }}">Lưu</button>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $services->links() }}</div>
@endsection
