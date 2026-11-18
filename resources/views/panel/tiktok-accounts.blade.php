@extends('layouts.panel')
@section('title', 'TikTok của tôi')
@section('content')
    <div class="page-hero">
        <div class="page-hero-body">
            <h2 class="page-hero-title">TikTok của tôi</h2>
            <p class="page-hero-desc">Liên kết tài khoản trước khi nạp xu. Tài khoản và đơn nạp chỉ hiển thị cho bạn.</p>
        </div>
        <a class="btn btn-outline" href="{{ route('panel.topup.create') }}">Nạp xu TikTok</a>
    </div>

    <div class="grid grid-2 mb-3">
        <section class="card">
            <h3 class="card-title">Liên kết bằng mã QR</h3>
            <p>Mở TikTok trên điện thoại để quét mã và xác nhận đăng nhập.</p>
            <p class="text-sm text-muted">Việc xác nhận sẽ liên kết phiên TikTok với Napxu.vn để thực hiện nạp xu. Chỉ quét cho tài khoản bạn sở hữu.</p>
            <button type="button" class="btn btn-primary" id="create-tiktok-qr" data-endpoint="{{ route('panel.tiktok.qr') }}" data-poll-base="{{ url('/tiktok-accounts/qr') }}">Tạo mã QR</button>
        </section>
        <section class="card">
            <h3 class="card-title">Liên kết bằng cookie</h3>
            <p class="text-sm text-muted">Cookie cho phép truy cập tài khoản TikTok. Cookie được gửi tới Napxu.vn; NhoSub không lưu cookie. Ưu tiên QR nếu không quen cách lấy cookie.</p>
            <form method="POST" action="{{ route('panel.tiktok.cookie') }}" data-guard>
                @csrf
                <div class="form-group">
                    <label class="form-label" for="tiktok-cookie">Cookie tài khoản của bạn</label>
                    <textarea class="form-textarea" id="tiktok-cookie" name="cookie" rows="3" maxlength="20000" autocomplete="off" required></textarea>
                </div>
                <label class="text-sm"><input type="checkbox" name="consent" value="1" required> Tôi sở hữu tài khoản và đồng ý gửi cookie tới Napxu.vn.</label>
                <div class="mt-2"><button class="btn btn-outline" type="submit">Liên kết bằng cookie</button></div>
            </form>
        </section>
    </div>

    <div class="card table-wrap">
        <table class="table">
            <thead><tr><th>Tài khoản</th><th class="num">Xu hiện có</th><th>Trạng thái</th><th>Cập nhật</th><th>Thao tác</th></tr></thead>
            <tbody>
            @forelse ($accounts as $account)
                <tr>
                    <td><strong>{{ $account->username }}</strong><div class="text-sm text-muted">{{ $account->nickname }}</div></td>
                    <td class="num">{{ number_format($account->coins, 0, ',', '.') }}</td>
                    <td>{{ $account->account_status }}</td>
                    <td class="text-sm text-muted">{{ $account->synced_at?->format('d/m/Y H:i') ?? 'Chưa cập nhật' }}</td>
                    <td>
                        <div class="flex gap-1 flex-wrap">
                            <form method="POST" action="{{ route('panel.tiktok.refresh', $account->id) }}">@csrf<button type="submit" class="btn btn-outline btn-sm">Cập nhật</button></form>
                            <form method="POST" action="{{ route('panel.tiktok.delete', $account->id) }}" onsubmit="return confirm('Gỡ liên kết trên NhoSub? Lịch sử đơn vẫn được giữ.');">@csrf<button type="submit" class="btn btn-ghost btn-sm">Gỡ liên kết</button></form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">Chưa liên kết tài khoản TikTok. Tạo mã QR hoặc nhập cookie để bắt đầu.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $accounts->links() }}</div>

    <dialog id="tiktok-qr-dialog" class="topup-qr-dialog" aria-labelledby="tiktok-qr-title">
        <div class="flex-between gap-2"><h3 id="tiktok-qr-title">Quét QR bằng TikTok</h3><button class="btn btn-ghost btn-sm" type="button" id="close-tiktok-qr">Đóng</button></div>
        <img id="tiktok-qr-image" width="256" height="256" alt="Mã QR liên kết tài khoản TikTok" hidden>
        <p id="tiktok-qr-status" role="status" aria-live="polite">Đang tạo mã QR…</p>
        <p class="text-sm text-muted">Xác nhận trên điện thoại. Không chia sẻ mã QR hoặc phiên đăng nhập.</p>
    </dialog>
@endsection
@push('scripts')
<script src="{{ asset('assets/js/tiktok-topup.js') }}" defer></script>
@endpush
