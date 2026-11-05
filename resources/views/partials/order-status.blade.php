@php
    // Map trạng thái (đơn + nạp tiền) sang nhãn tiếng Việt + class badge.
    $map = [
        'pending'      => ['Chờ xử lý', 'badge-pending'],
        'charged'      => ['Đã trừ tiền', 'badge-pending'],
        'processing'   => ['Đang chạy', 'badge-processing'],
        'completed'    => ['Hoàn thành', 'badge-completed'],
        'partial'      => ['Hoàn thành một phần', 'badge-partial'],
        'canceled'     => ['Đã hủy', 'badge-canceled'],
        'error'        => ['Lỗi', 'badge-error'],
        'stuck_review' => ['Chờ admin xử lý', 'badge-review'],
        'approved'     => ['Đã duyệt', 'badge-completed'],
        'rejected'     => ['Từ chối', 'badge-canceled'],
        'expired'      => ['Hết hạn', 'badge-neutral'],
        'paid'         => ['Đã thanh toán', 'badge-completed'],
        // Trạng thái webhook (lịch sử nạp SePay)
        'processed'    => ['Đã cộng ví', 'badge-completed'],
        'unmatched'    => ['Không khớp', 'badge-review'],
    ];
    $entry = $map[$status] ?? [$status, 'badge-neutral'];
@endphp
<span class="badge {{ $entry[1] }}">{{ $entry[0] }}</span>
