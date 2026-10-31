@extends('layouts.panel')
@section('title', 'Nhật ký webhook')
@section('content')
    <form method="GET" class="mb-2 flex gap-1 flex-wrap">
        <select class="form-select" name="status" style="max-width:220px;" onchange="this.form.submit()">
            <option value="">Tất cả trạng thái</option>
            @foreach (['processed','duplicate','unmatched','rejected'] as $s)
                <option value="{{ $s }}" @selected($status === $s)>{{ $s }}</option>
            @endforeach
        </select>
    </form>

    <div class="card" style="padding:0;overflow:hidden;">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>#</th><th>Cổng</th><th>Mã GD</th><th class="num">Số tiền</th><th>Nội dung</th><th>Trạng thái</th><th>Lý do</th><th>Xử lý</th></tr>
                </thead>
                <tbody>
                @foreach ($logs as $log)
                    <tr>
                        <td>{{ $log->id }}</td>
                        <td class="text-sm">{{ strtoupper($log->gateway) }}</td>
                        <td class="mono text-sm">{{ $log->gateway_txn_id }}</td>
                        <td class="num">{{ number_format($log->amount, 0, ',', '.') }} ₫</td>
                        <td class="truncate text-sm" title="{{ $log->content }}">{{ $log->content }}</td>
                        <td>
                            <span class="badge {{ $log->status === 'processed' ? 'badge-completed' : ($log->status === 'duplicate' ? 'badge-neutral' : 'badge-error') }}">
                                {{ $log->status }}
                            </span>
                        </td>
                        <td class="text-sm text-muted">{{ $log->reason }}</td>
                        <td>
                            @if ($log->status === 'unmatched' && $log->amount > 0)
                                <form method="POST" action="{{ route('admin.webhooks.credit', $log) }}" class="flex gap-1" data-guard>
                                    @csrf
                                    <input class="form-input" name="username" placeholder="username" style="min-height:34px;padding:4px 8px;width:130px;" required>
                                    <input class="form-input" name="reason" placeholder="lý do" style="min-height:34px;padding:4px 8px;width:140px;" required>
                                    <button class="btn btn-primary btn-sm" type="submit">Cộng tay</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $logs->links() }}</div>
@endsection
