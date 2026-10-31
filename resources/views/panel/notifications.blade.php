@extends('layouts.panel')
@section('title', 'Thông báo')
@section('content')
    <div class="flex-between mb-2">
        <h2 style="font-size:18px;margin:0;">Tất cả thông báo</h2>
        <form method="POST" action="{{ route('panel.notifications.readAll') }}">
            @csrf
            <button class="btn btn-ghost btn-sm" type="submit">Đánh dấu tất cả đã đọc</button>
        </form>
    </div>

    <div class="card" style="padding:0;overflow:hidden;">
        @forelse ($notifications as $n)
            <div style="padding:16px 20px;border-bottom:1px solid #EDF2FA;{{ $n->read_at ? '' : 'background:#F5F8FF;' }}">
                <div class="flex-between">
                    <strong>{{ $n->data['title'] ?? 'Thông báo' }}</strong>
                    <span class="text-sm text-muted">{{ $n->created_at->format('d/m/Y H:i') }}</span>
                </div>
                <div class="text-sm text-muted mt-1">{{ $n->data['message'] ?? '' }}</div>
            </div>
        @empty
            <div class="empty-state">Chưa có thông báo nào.</div>
        @endforelse
    </div>

    <div class="mt-3">{{ $notifications->links() }}</div>
@endsection
