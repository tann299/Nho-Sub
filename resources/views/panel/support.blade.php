@extends('layouts.panel')
@section('title', 'Trung tâm hỗ trợ')
@section('content')
    <div class="grid grid-2">
        <div class="card">
            <h2 class="card-title">Hướng dẫn nhanh</h2>
            <ol style="padding-left:18px;color:var(--muted);">
                <li><strong style="color:var(--text);">Nạp tiền</strong> — chuyển khoản đúng nội dung <span class="mono">NHOSUB &lt;TÊN ĐĂNG NHẬP&gt;</span>.</li>
                <li><strong style="color:var(--text);">Chọn dịch vụ</strong> — xem giá theo 1.000 lượt, giới hạn số lượng.</li>
                <li><strong style="color:var(--text);">Đặt đơn</strong> — dán link, nhập số lượng, xem trước rồi xác nhận.</li>
                <li><strong style="color:var(--text);">Theo dõi</strong> — trạng thái tự cập nhật mỗi 5 phút.</li>
            </ol>
        </div>

        <div class="card">
            <h2 class="card-title">Câu hỏi thường gặp</h2>

            <details class="faq-item">
                <summary>Đơn bị hủy hoặc chạy một phần thì sao?</summary>
                <div class="faq-body">Hệ thống tự hoàn tiền phần chưa chạy vào ví của bạn trong vòng 5 phút sau khi nhà cung cấp cập nhật trạng thái.</div>
            </details>
            <details class="faq-item">
                <summary>Bao lâu thì đơn bắt đầu chạy?</summary>
                <div class="faq-body">Thời gian bắt đầu trung bình ghi trên từng dịch vụ, thường từ 0–60 phút.</div>
            </details>
            <details class="faq-item">
                <summary>Nạp tiền bao lâu thì vào ví?</summary>
                <div class="faq-body">Chuyển khoản đúng nội dung thường được cộng tự động trong vài giây. Nếu sau 15 phút chưa thấy, gửi yêu cầu nạp ở trang Nạp tiền.</div>
            </details>
        </div>
    </div>

    <div class="card mt-3">
        <h3 class="card-title">Cần hỗ trợ thêm?</h3>
        <p class="text-muted mb-0">Email: hotro@nhosub.vn · Thời gian hỗ trợ: 8:00 – 22:00 hằng ngày.</p>
    </div>
@endsection
