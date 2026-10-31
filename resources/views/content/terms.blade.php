@extends(auth()->check() ? 'layouts.panel' : 'layouts.landing')
@section('title', 'Điều khoản sử dụng')
@section('content')
    @if (auth()->check())
        <div style="max-width:820px;">
    @else
        <section class="section"><div class="container"><div style="max-width:820px;">
    @endif

    <div class="card">
        <h1 style="font-size:24px;">Điều khoản sử dụng</h1>
        <p class="text-sm text-muted">Cập nhật lần cuối: 31/10/2026</p>

        <h3>1. Giới thiệu dịch vụ</h3>
        <p class="text-muted">
            NhoSub cung cấp dịch vụ tăng tương tác mạng xã hội (like, follow, view, comment, share, member)
            cho Facebook, TikTok, Instagram, YouTube và các nền tảng khác. Bằng việc đăng ký và sử dụng,
            bạn đồng ý với các điều khoản dưới đây.
        </p>

        <h3>2. Tài khoản</h3>
        <ul class="text-muted">
            <li>Mỗi người dùng chỉ được tạo một tài khoản. Tài khoản vi phạm sẽ bị khoá không hoàn tiền.</li>
            <li>Bạn chịu trách nhiệm bảo mật mật khẩu. Mọi giao dịch phát sinh từ tài khoản của bạn được coi là do bạn thực hiện.</li>
            <li>Không cung cấp thông tin sai sự thật khi đăng ký.</li>
        </ul>

        <h3>3. Nạp tiền và hoàn tiền</h3>
        <ul class="text-muted">
            <li>Số dư ví dùng để mua dịch vụ trên hệ thống, <strong>không quy đổi ngược thành tiền mặt</strong> và không hỗ trợ rút lại sau khi nạp.</li>
            <li>Nạp tối thiểu 10.000 ₫ mỗi giao dịch. Chuyển khoản đúng nội dung <span class="mono">NHOSUB &lt;TÊN ĐĂNG NHẬP&gt;</span> để được cộng tự động.</li>
            <li>Đơn bị huỷ hoặc chạy một phần sẽ được hoàn tiền phần chưa chạy vào ví tự động.</li>
        </ul>

        <h3>4. Đơn hàng</h3>
        <ul class="text-muted">
            <li>Bạn chịu trách nhiệm về link đặt đơn: chỉ đặt link công khai, thuộc quyền sở hữu hoặc được phép tương tác.</li>
            <li>Đơn đặt lên link riêng tư, sai link, hoặc vi phạm chính sách nền tảng sẽ không được hoàn tiền.</li>
            <li>Thời gian hoàn thành ghi trên từng dịch vụ là ước lượng, không phải cam kết chính xác.</li>
        </ul>

        <h3>5. Chịu trách nhiệm</h3>
        <ul class="text-muted">
            <li>NhoSub không chịu trách nhiệm với thay đổi chính sách của các nền tảng mạng xã hội ảnh hưởng đến kết quả dịch vụ.</li>
            <li>Không sử dụng dịch vụ cho mục đích gian lận, lừa đảo hoặc vi phạm pháp luật Việt Nam.</li>
        </ul>

        <h3>6. Liên hệ</h3>
        <p class="text-muted">Mọi thắc mắc về điều khoản: hotro@nhosub.vn · 8:00 – 22:00 hằng ngày.</p>
    </div>

    @if (! auth()->check())
    </div></div></section>
    @else
    </div>
    @endif
@endsection
