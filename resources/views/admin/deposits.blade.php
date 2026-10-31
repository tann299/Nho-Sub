@extends('layouts.panel')
@section('title', 'Duyệt nạp tiền')
@section('content')
    <div class="page-hero">
        <div class="page-hero-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 3v12m0 0l4-4m-4 4l-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
        </div>
        <div class="page-hero-body">
            <h2 class="page-hero-title">Duyệt nạp tiền</h2>
            <p class="page-hero-desc">Đối chiếu sao kê trước khi duyệt — mỗi yêu cầu chỉ cộng tiền đúng một lần.</p>
        </div>
    </div>

    <div class="flex-between mb-2 flex-wrap gap-1">
        <div class="flex gap-1 flex-wrap">
            @foreach (['pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Từ chối', 'expired' => 'Hết hạn', '' => 'Tất cả'] as $val => $label)
                <a href="{{ route('admin.deposits.index', ['status' => $val]) }}"
                   class="btn btn-sm {{ $status === $val ? 'btn-primary' : 'btn-ghost' }}">{{ $label }}</a>
            @endforeach
        </div>
        @if ($pendingCount > 0)
            <span class="badge badge-pending">{{ $pendingCount }} yêu cầu chờ duyệt</span>
        @endif
    </div>

    <div class="card" style="padding:0;overflow:hidden;">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>#</th><th>Khách</th><th class="num">Số tiền</th><th>Phương thức</th><th>Trạng thái</th><th>Thời gian</th><th>Xử lý</th></tr>
                </thead>
                <tbody>
                @forelse ($requests as $r)
                    <tr>
                        <td>{{ $r->id }}</td>
                        <td>
                            <strong>{{ $r->user->username ?? '—' }}</strong>
                            <div class="text-sm text-muted">{{ $r->user->email ?? '' }}</div>
                        </td>
                        <td class="num"><strong>{{ number_format($r->amount, 0, ',', '.') }} ₫</strong></td>
                        <td class="text-sm">{{ $r->method === 'payos' ? 'PayOS' : 'Chuyển khoản' }}</td>
                        <td>@include('partials.order-status', ['status' => $r->status])</td>
                        <td class="text-sm text-muted nowrap">{{ $r->created_at?->format('d/m/Y H:i') }}</td>
                        <td>
                            @if ($r->status === 'pending')
                                <div class="flex gap-1 flex-wrap">
                                    <form method="POST" action="{{ route('admin.deposits.approve', $r) }}" data-guard
                                          data-confirm="Xác nhận đã nhận đủ {{ number_format($r->amount, 0, ',', '.') }} ₫?">
                                        @csrf
                                        <button class="btn btn-primary btn-sm" type="submit">Duyệt</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.deposits.reject', $r) }}" data-guard>
                                        @csrf
                                        <input type="hidden" name="reason" value="">
                                        <button class="btn btn-ghost btn-sm" type="submit"
                                                onclick="this.form.reason.value = prompt('Lý do từ chối (bắt buộc):') || '';">
                                            Từ chối
                                        </button>
                                    </form>
                                </div>
                            @else
                                <span class="text-sm text-muted">{{ $r->admin_note ?: '—' }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted" style="padding:40px;">Không có yêu cầu nào.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $requests->links() }}</div>
@endsection
