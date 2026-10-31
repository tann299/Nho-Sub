@extends('layouts.panel')
@section('title', 'Quản lý người dùng')
@section('content')
    <div class="page-hero">
        <div class="page-hero-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/></svg>
        </div>
        <div class="page-hero-body">
            <h2 class="page-hero-title">Người dùng</h2>
            <p class="page-hero-desc">Khoá/mở tài khoản, cộng trừ ví — mọi điều chỉnh đều ghi sổ và yêu cầu lý do.</p>
        </div>
    </div>

    <form method="GET" class="card mb-3">
        <div class="grid grid-3" style="align-items:end;">
            <div class="form-group mb-0">
                <label class="form-label" for="q">Tìm kiếm</label>
                <input class="form-input" id="q" name="q" value="{{ $q }}" placeholder="Tên đăng nhập, email, SĐT">
            </div>
            <div class="form-group mb-0">
                <label class="form-label" for="role">Vai trò</label>
                <select class="form-select" id="role" name="role">
                    <option value="">Tất cả</option>
                    <option value="customer" @selected($role === 'customer')>Khách hàng</option>
                    <option value="admin" @selected($role === 'admin')>Quản trị viên</option>
                </select>
            </div>
            <div class="form-group mb-0"><button class="btn btn-primary btn-block">Tìm</button></div>
        </div>
    </form>

    <div class="card" style="padding:0;overflow:hidden;">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>ID</th><th>Tên đăng nhập</th><th>Email</th><th>Vai trò</th><th class="num">Số dư</th><th>Trạng thái</th><th></th></tr>
                </thead>
                <tbody>
                @foreach ($users as $u)
                    <tr>
                        <td>{{ $u->id }}</td>
                        <td><strong>{{ $u->username }}</strong><div class="text-sm text-muted">{{ $u->full_name }}</div></td>
                        <td class="text-sm">{{ $u->email }}</td>
                        <td><span class="badge {{ $u->role === 'admin' ? 'badge-processing' : 'badge-neutral' }}">{{ $u->role === 'admin' ? 'Quản trị' : 'Khách' }}</span></td>
                        <td class="num">{{ number_format($u->balance, 0, ',', '.') }} ₫</td>
                        <td><span class="badge {{ $u->status === 'active' ? 'badge-completed' : 'badge-canceled' }}">{{ $u->status === 'active' ? 'Hoạt động' : 'Đã khoá' }}</span></td>
                        <td class="nowrap">
                            <a class="btn btn-ghost btn-sm" href="{{ route('admin.users.show', $u) }}">Chi tiết</a>
                            <form method="POST" action="{{ route('admin.users.toggle', $u) }}" style="display:inline;"
                                  data-confirm="Bạn chắc chắn muốn {{ $u->status === 'active' ? 'khoá' : 'mở khoá' }} tài khoản này?">
                                @csrf
                                <button class="btn btn-sm {{ $u->status === 'active' ? 'btn-danger' : 'btn-outline' }}" type="submit">
                                    {{ $u->status === 'active' ? 'Khoá' : 'Mở khoá' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $users->links() }}</div>
@endsection
