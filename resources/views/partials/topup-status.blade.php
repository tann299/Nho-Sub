@php
    $labels = ['pending' => 'Chờ nạp', 'charged' => 'Đã trừ ví', 'processing' => 'Đang nạp',
        'success' => 'Hoàn thành', 'failed' => 'Thất bại', 'refunded' => 'Đã hoàn tiền',
        'canceled' => 'Đã hủy', 'stuck_review' => 'Chờ đối soát'];
    $badge = ['pending' => 'pending', 'charged' => 'review', 'processing' => 'processing',
        'success' => 'completed', 'failed' => 'error', 'refunded' => 'partial',
        'canceled' => 'canceled', 'stuck_review' => 'review'];
@endphp
<span class="badge badge-{{ $badge[$status] ?? 'neutral' }}">{{ $labels[$status] ?? $status }}</span>
