@extends('layouts.panel')
@section('title', 'Trung tâm hỗ trợ')
@section('content')
    <div class="page-hero">
        <div class="page-hero-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9.1 9a3 3 0 1 1 4.6 2.5c-.9.6-1.7 1.3-1.7 2.5M12 17h.01"/></svg>
        </div>
        <div class="page-hero-body">
            <h2 class="page-hero-title">Trung tâm hỗ trợ</h2>
            <p class="page-hero-desc">Hướng dẫn 4 bước dùng panel và câu trả lời cho các vướng mắc thường gặp.</p>
        </div>
    </div>

    <div class="grid grid-2">
        <div class="card">
            <span class="card-label">Hướng dẫn nhanh</span>

            <div class="step-row">
                <span class="step-num">1</span>
                <span class="step-text"><strong>Nạp tiền</strong> — chuyển khoản đúng nội dung <span class="mono">NHOSUB &lt;TÊN ĐĂNG NHẬP&gt;</span>.</span>
            </div>
            <div class="step-row">
                <span class="step-num">2</span>
                <span class="step-text"><strong>Chọn dịch vụ</strong> — giá hiển thị theo 1.000 lượt, kèm giới hạn số lượng.</span>
            </div>
            <div class="step-row">
                <span class="step-num">3</span>
                <span class="step-text"><strong>Đặt đơn</strong> — dán link, nhập số lượng, xem trước rồi xác nhận.</span>
            </div>
            <div class="step-row">
                <span class="step-num">4</span>
                <span class="step-text"><strong>Theo dõi</strong> — trạng thái tự cập nhật mỗi 5 phút, hoàn tiền phần chưa chạy.</span>
            </div>

            <div class="divider"></div>

            <div class="deep-block" style="padding:18px 20px;">
                <h3>Sẵn sàng đặt đơn đầu tiên?</h3>
                <p>Từ 100 lượt, giá từ vài nghìn đồng.</p>
                <a href="{{ route('panel.orders.create') }}" class="btn btn-primary">Đặt đơn ngay</a>
            </div>
        </div>

        <div>
            <div class="card mb-2">
                <span class="card-label">Câu hỏi thường gặp</span>

                <details class="faq-item" style="box-shadow:none;">
                    <summary>Đơn bị hủy hoặc chạy một phần thì sao?</summary>
                    <div class="faq-body">Hệ thống tự hoàn tiền phần chưa chạy vào ví trong vòng 5 phút sau khi nhà cung cấp cập nhật trạng thái.</div>
                </details>
                <details class="faq-item" style="box-shadow:none;">
                    <summary>Bao lâu thì đơn bắt đầu chạy?</summary>
                    <div class="faq-body">Thời gian bắt đầu trung bình ghi trên từng dịch vụ, thường từ 0–60 phút.</div>
                </details>
                <details class="faq-item" style="box-shadow:none;">
                    <summary>Nạp tiền bao lâu thì vào ví?</summary>
                    <div class="faq-body">Chuyển khoản đúng nội dung thường vào ví trong vài giây. Quá 15 phút chưa thấy, gửi yêu cầu ở trang Nạp tiền.</div>
                </details>
                <details class="faq-item" style="box-shadow:none;margin-bottom:0;">
                    <summary>Tôi có thể rút lại tiền đã nạp không?</summary>
                    <div class="faq-body">Không — tiền đã nạp dùng để mua dịch vụ trên hệ thống, xem <a href="{{ route('content.terms') }}">Điều khoản sử dụng</a>.</div>
                </details>
            </div>

            <div class="note-block" style="margin-bottom:0;">
                Cần hỗ trợ thêm? Email <strong>hotro@nhosub.vn</strong> · 8:00 – 22:00 hằng ngày.
            </div>
        </div>
    </div>
@endsection
