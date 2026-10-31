@extends('layouts.panel')
@section('title', 'Nhật ký API')
@section('content')
    <form method="GET" class="mb-2 flex gap-1 flex-wrap">
        <select class="form-select" name="action" style="max-width:200px;" onchange="this.form.submit()">
            <option value="">Tất cả hành động</option>
            @foreach ($actions as $a)
                <option value="{{ $a }}" @selected($action === $a)>{{ $a }}</option>
            @endforeach
        </select>
        <select class="form-select" name="success" style="max-width:160px;" onchange="this.form.submit()">
            <option value="">Mọi kết quả</option>
            <option value="1" @selected((string) $success === '1')>Thành công</option>
            <option value="0" @selected((string) $success === '0')>Thất bại</option>
        </select>
    </form>

    <div class="card" style="padding:0;overflow:hidden;">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>#</th><th>Thời gian</th><th>Hành động</th><th>Đơn</th><th>Kết quả</th></tr></thead>
                <tbody>
                @foreach ($logs as $log)
                    <tr>
                        <td>{{ $log->id }}</td>
                        <td class="text-sm text-muted nowrap">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                        <td class="text-sm">{{ $log->action }}</td>
                        <td class="text-sm">
                            @if ($log->order_id)
                                <a class="link-underline" href="{{ route('admin.orders.show', $log->order_id) }}">#{{ $log->order_id }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-sm">
                            <span class="badge {{ $log->success ? 'badge-completed' : 'badge-error' }}">{{ $log->success ? 'OK' : 'Lỗi' }}</span>
                            <div class="text-sm text-muted">{{ $log->response_summary }}</div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $logs->links() }}</div>
@endsection
