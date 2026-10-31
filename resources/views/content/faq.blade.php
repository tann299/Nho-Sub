@extends(auth()->check() ? 'layouts.panel' : 'layouts.landing')
@section('title', 'Câu hỏi thường gặp')
@section('content')
    @if (auth()->check())
        <div style="max-width:820px;">
    @else
        <section class="section"><div class="container"><div style="max-width:820px;">
    @endif

    <h1 style="font-size:24px;" class="mb-3">Câu hỏi thường gặp</h1>

    @foreach ([
        ['Nạp tiền bao lâu thì vào ví?', 'Chuyển khoản đúng nội dung NHOSUB <TÊN ĐĂNG NHẬP> thường được cộng tự động trong vài giây. Nếu sau 15 phút chưa thấy, bạn gửi yêu cầu nạp ở trang Nạp tiền để admin kiểm tra.'],
        ['Tôi có thể rút lại tiền đã nạp không?', 'Không. Tiền đã nạp chỉ dùng để mua dịch vụ trên hệ thống, không quy đổi ngược thành tiền mặt (theo điều khoản sử dụng).'],
        ['Đơn chạy một phần thì tính tiền thế nào?', 'Bạn chỉ trả cho phần đã chạy. Phần chưa chạy được hoàn lại ví tự động, tối đa trong 5 phút sau khi nhà cung cấp cập nhật trạng thái.'],
        ['Bao lâu thì đơn bắt đầu chạy?', 'Thời gian bắt đầu trung bình ghi trên từng dịch vụ, thường từ 0–60 phút.'],
        ['Đơn của tôi bị lỗi thì sao?', 'Nếu nhà cung cấp không nhận đơn, hệ thống hoàn 100% tiền vào ví ngay và đánh dấu đơn lỗi. Bạn có thể đặt lại.'],
        ['Tôi có thể đặt link bất kỳ không?', 'Chỉ đặt link công khai mà bạn sở hữu hoặc được phép tăng tương tác. Đơn vi phạm sẽ không được hoàn tiền.'],
    ] as [$q, $a])
        <details class="faq-item">
            <summary>{{ $q }}</summary>
            <div class="faq-body">{{ $a }}</div>
        </details>
    @endforeach

    @if (! auth()->check())
    </div></div></section>
    @else
    </div>
    @endif
@endsection
