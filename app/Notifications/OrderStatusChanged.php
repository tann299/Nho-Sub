<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderStatusChanged extends Notification
{
    use Queueable;

    public function __construct(private readonly Order $order, private readonly string $status)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $label = match ($this->status) {
            Order::STATUS_COMPLETED => 'đã hoàn thành',
            Order::STATUS_PARTIAL => 'hoàn thành một phần',
            Order::STATUS_CANCELED => 'đã bị hủy',
            Order::STATUS_ERROR => 'gặp lỗi',
            default => 'đã cập nhật',
        };

        return [
            'title' => 'Đơn #' . $this->order->id . ' ' . $label,
            'message' => 'Trạng thái đơn hàng của bạn vừa thay đổi.',
            'url' => route('panel.orders.show', $this->order),
        ];
    }
}
