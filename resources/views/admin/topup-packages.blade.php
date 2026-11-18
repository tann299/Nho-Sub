@extends('layouts.panel')
@section('title', 'Gói nạp xu TikTok')
@section('content')
    <div class="page-hero">
        <div class="page-hero-body">
            <h2 class="page-hero-title">Gói nạp xu TikTok</h2>
            <p class="page-hero-desc">Nhập mã gói, máy chủ và giá theo napxu.vn. Giá được chốt vào đơn khi khách đặt — sửa giá không đổi đơn cũ.</p>
        </div>
    </div>

    <div class="card mb-3">
        <h3 class="card-title">Thêm gói mới</h3>
        <form method="POST" action="{{ route('admin.topupPackages.store') }}" data-guard>
            @csrf
            <div class="grid grid-4" style="align-items:end;">
                <div class="form-group mb-0">
                    <label class="form-label" for="pkg-name">Tên gói</label>
                    <input class="form-input" id="pkg-name" name="name" value="{{ old('name') }}" required maxlength="120">
                </div>
                <div class="form-group mb-0">
                    <label class="form-label" for="pkg-coins">Số xu</label>
                    <input class="form-input" id="pkg-coins" name="coins" type="number" min="1" value="{{ old('coins') }}" required>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label" for="pkg-cost">Giá gốc Napxu (₫)</label>
                    <input class="form-input" id="pkg-cost" name="cost_price" type="number" min="1" value="{{ old('cost_price') }}" required>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label" for="pkg-sell">Giá bán (₫)</label>
                    <input class="form-input" id="pkg-sell" name="sell_price" type="number" min="1" value="{{ old('sell_price') }}" required>
                </div>
            </div>
            <div class="grid grid-4 mt-2" style="align-items:end;">
                <div class="form-group mb-0">
                    <label class="form-label" for="pkg-server">Máy chủ (vd server6)</label>
                    <input class="form-input" id="pkg-server" name="server" value="{{ old('server', 'server1') }}" required>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label" for="pkg-napxu-id">Mã gói trên Napxu</label>
                    <input class="form-input" id="pkg-napxu-id" name="napxu_package_id" type="number" min="1" value="{{ old('napxu_package_id') }}" required>
                </div>
                <label class="text-sm" style="padding-bottom:9px;">
                    <input type="checkbox" name="is_active" value="1" checked> Mở bán ngay
                </label>
                <button class="btn btn-primary" type="submit">Thêm gói</button>
            </div>
        </form>
    </div>

    <div class="card" style="padding:0;overflow:hidden;">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Gói</th><th class="num">Xu</th><th class="num">Giá gốc</th><th class="num">Giá bán</th><th>Máy chủ</th><th>Mã Napxu</th><th>Bán</th><th></th></tr></thead>
                <tbody>
                @forelse ($packages as $pkg)
                    <tr>
                        <td>
                            <form method="POST" action="{{ route('admin.topupPackages.update', $pkg) }}" id="pf-{{ $pkg->id }}">
                                @csrf
                                <input class="form-input" name="name" value="{{ $pkg->name }}" style="min-height:36px;padding:6px 10px;">
                            </form>
                        </td>
                        <td class="num"><input class="form-input" form="pf-{{ $pkg->id }}" name="coins" type="number" min="1" value="{{ $pkg->coins }}" style="width:90px;"></td>
                        <td class="num"><input class="form-input" form="pf-{{ $pkg->id }}" name="cost_price" type="number" min="1" value="{{ $pkg->cost_price }}" style="width:110px;text-align:right;"></td>
                        <td class="num"><input class="form-input" form="pf-{{ $pkg->id }}" name="sell_price" type="number" min="1" value="{{ $pkg->sell_price }}" style="width:110px;text-align:right;"></td>
                        <td><input class="form-input" form="pf-{{ $pkg->id }}" name="server" value="{{ $pkg->server }}" style="width:100px;"></td>
                        <td><input class="form-input" form="pf-{{ $pkg->id }}" name="napxu_package_id" type="number" min="1" value="{{ $pkg->napxu_package_id }}" style="width:80px;"></td>
                        <td>
                            <input type="hidden" form="pf-{{ $pkg->id }}" name="is_active" value="0">
                            <input type="checkbox" form="pf-{{ $pkg->id }}" name="is_active" value="1" @checked($pkg->is_active)>
                        </td>
                        <td>
                            <div class="flex gap-1">
                                <button class="btn btn-outline btn-sm" type="submit" form="pf-{{ $pkg->id }}">Lưu</button>
                                <form method="POST" action="{{ route('admin.topupPackages.toggle', $pkg) }}">@csrf<button class="btn btn-ghost btn-sm" type="submit">{{ $pkg->is_active ? 'Ngừng bán' : 'Mở bán' }}</button></form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8">Chưa có gói nào. Thêm gói đầu tiên theo giá napxu.vn đang bán.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $packages->links() }}</div>
@endsection
