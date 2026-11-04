@extends('layouts.panel')
@section('title', 'Trung tâm hỗ trợ')
@section('content')
    <div class="page-hero">
        <div class="page-hero-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9.1 9a3 3 0 1 1 4.6 2.5c-.9.6-1.7 1.3-1.7 2.5M12 17h.01"/></svg>
        </div>
        <div class="page-hero-body">
            <h2 class="page-hero-title">Trung tâm hỗ trợ</h2>
            <p class="page-hero-desc">Hướng dẫn sử dụng, câu hỏi thường gặp và điều khoản — tất cả ở một nơi.</p>
        </div>
    </div>

    {{-- Tab chuyển nội dung --}}
    @php $tab = request()->query('tab', 'guide'); @endphp
    <div class="flex gap-1 flex-wrap mb-2">
        <a href="{{ route('panel.support.index', ['tab' => 'guide']) }}"
           class="btn btn-sm {{ $tab === 'guide' ? 'btn-primary' : 'btn-ghost' }}">Hướng dẫn</a>
        <a href="{{ route('panel.support.index', ['tab' => 'faq']) }}"
           class="btn btn-sm {{ $tab === 'faq' ? 'btn-primary' : 'btn-ghost' }}">Câu hỏi thường gặp</a>
        <a href="{{ route('panel.support.index', ['tab' => 'terms']) }}"
           class="btn btn-sm {{ $tab === 'terms' ? 'btn-primary' : 'btn-ghost' }}">Điều khoản sử dụng</a>
    </div>

    @if ($tab === 'faq')
        {{-- Câu hỏi thường gặp --}}
        <div class="card">
            <span class="card-label">Câu hỏi thường gặp</span>

            @foreach ([
                ['Nạp tiền bao lâu thì vào ví?', 'Chuyển khoản đúng nội dung NHOSUB <TÊN ĐĂNG NHẬP> thường vào ví trong vài giây. Quá 15 phút chưa thấy, gửi yêu cầu ở trang Nạp tiền.'],
                ['Tôi có thể rút lại tiền đã nạp không?', 'Không — tiền đã nạp dùng để mua dịch vụ trên hệ thống, không quy đổi ngược thành tiền mặt.'],
                ['Đơn chạy một phần thì tính tiền thế nào?', 'Bạn chỉ trả cho phần đã chạy. Phần chưa chạy được hoàn lại ví tự động trong 5 phút sau khi nhà cung cấp cập nhật trạng thái.'],
                ['Bao lâu thì đơn bắt đầu chạy?', 'Thời gian bắt đầu trung bình ghi trên từng dịch vụ, thường từ 0–60 phút.'],
                ['Đơn của tôi bị lỗi thì sao?', 'Nếu nhà cung cấp không nhận đơn, hệ thống hoàn 100% tiền vào ví ngay và đánh dấu đơn lỗi. Bạn có thể đặt lại.'],
                ['Tôi có thể đặt link bất kỳ không?', 'Chỉ đặt link công khai mà bạn sở hữu hoặc được phép tăng tương tác. Đơn vi phạm sẽ không được hoàn tiền.'],
                ['Tại sao đơn không chạy?', 'Có thể link riêng tư, sai link, hoặc dịch vụ đang bảo trì. Kiểm tra trạng thái đơn rồi gửi yêu cầu hỗ trợ kèm mã đơn.'],
            ] as [$q, $a])
                <details class="faq-item" style="box-shadow:none;">
                    <summary>{{ $q }}</summary>
                    <div class="faq-body">{{ $a }}</div>
                </details>
            @endforeach

            <div class="note-block mt-2" style="margin-bottom:0;">
                Chưa có câu trả lời? Email <strong>hotro@nhosub.vn</strong> · 8:00 – 22:00 hằng ngày.
            </div>
        </div>

    @elseif ($tab === 'terms')
        {{-- Điều khoản sử dụng --}}
        <div class="card">
            <span class="card-label">Điều khoản sử dụng</span>
            <p class="text-sm text-muted">Cập nhật lần cuối: 01/11/2026</p>

            <h3 style="font-size:14px;margin-top:16px;">1. Giới thiệu dịch vụ</h3>
            <p class="text-sm text-muted">
                NhoSub cung cấp dịch vụ tăng tương tác mạng xã hội (like, follow, view, comment, share, member)
                cho Facebook, TikTok, Instagram, YouTube và các nền tảng khác. Bằng việc đăng ký và sử dụng,
                bạn đồng ý với các điều khoản dưới đây.
            </p>

            <h3 style="font-size:14px;margin-top:16px;">2. Tài khoản</h3>
            <ul class="text-sm text-muted" style="padding-left:18px;">
                <li>Mỗi người dùng chỉ được tạo một tài khoản. Tài khoản vi phạm sẽ bị khoá không hoàn tiền.</li>
                <li>Bạn chịu trách nhiệm bảo mật mật khẩu. Mọi giao dịch từ tài khoản của bạn được coi là do bạn thực hiện.</li>
                <li>Không cung cấp thông tin sai sự thật khi đăng ký.</li>
            </ul>

            <h3 style="font-size:14px;margin-top:16px;">3. Nạp tiền và hoàn tiền</h3>
            <ul class="text-sm text-muted" style="padding-left:18px;">
                <li>Số dư ví dùng để mua dịch vụ, <strong>không quy đổi ngược thành tiền mặt</strong> và không hỗ trợ rút lại sau khi nạp.</li>
                <li>Nạp tối thiểu 10.000 ₫ mỗi giao dịch. Chuyển khoản đúng nội dung <span class="mono">NHOSUB &lt;TÊN ĐĂNG NHẬP&gt;</span> để được cộng tự động.</li>
                <li>Đơn bị huỷ hoặc chạy một phần sẽ được hoàn tiền phần chưa chạy vào ví tự động.</li>
            </ul>

            <h3 style="font-size:14px;margin-top:16px;">4. Đơn hàng</h3>
            <ul class="text-sm text-muted" style="padding-left:18px;">
                <li>Bạn chịu trách nhiệm về link đặt đơn: chỉ đặt link công khai, thuộc quyền sở hữu hoặc được phép tương tác.</li>
                <li>Đơn đặt lên link riêng tư, sai link, hoặc vi phạm chính sách nền tảng sẽ không được hoàn tiền.</li>
                <li>Thời gian hoàn thành ghi trên từng dịch vụ là ước lượng, không phải cam kết chính xác.</li>
            </ul>

            <h3 style="font-size:14px;margin-top:16px;">5. Chịu trách nhiệm</h3>
            <ul class="text-sm text-muted" style="padding-left:18px;">
                <li>NhoSub không chịu trách nhiệm với thay đổi chính sách của các nền tảng mạng xã hội ảnh hưởng đến kết quả dịch vụ.</li>
                <li>Không sử dụng dịch vụ cho mục đích gian lận, lừa đảo hoặc vi phạm pháp luật Việt Nam.</li>
            </ul>

            <h3 style="font-size:14px;margin-top:16px;">6. Liên hệ</h3>
            <p class="text-sm text-muted" style="margin-bottom:0;">Mọi thắc mắc về điều khoản: hotro@nhosub.vn · 8:00 – 22:00 hằng ngày.</p>
        </div>

    @else
        {{-- Hướng dẫn sử dụng --}}
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

                <div class="deep-block" style="padding:16px 18px;">
                    <h3>Sẵn sàng đặt đơn đầu tiên?</h3>
                    <p>Từ 100 lượt, giá từ vài nghìn đồng.</p>
                    <a href="{{ route('panel.orders.create') }}" class="btn btn-primary">Đặt đơn ngay</a>
                </div>
            </div>

            <div>
                <div class="card mb-2">
                    <span class="card-label">Liên hệ hỗ trợ</span>
                    <div class="copy-row">
                        <span class="copy-label">Email</span>
                        <span class="copy-value mono">hotro@nhosub.vn</span>
                    </div>
                    <div class="copy-row" style="margin-bottom:0;">
                        <span class="copy-label">Thời gian</span>
                        <span class="copy-value">8:00 – 22:00 hằng ngày</span>
                    </div>
                </div>

                <div class="note-block" style="margin-bottom:0;">
                    Khi liên hệ, gửi kèm <strong>mã đơn</strong> và <strong>link</strong> để được hỗ trợ nhanh nhất.
                </div>
            </div>
        </div>
    @endif
@endsection
