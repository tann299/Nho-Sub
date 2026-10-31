<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BalanceAdjusted extends Notification
{
    use Queueable;

    public function __construct(private readonly int $amount, private readonly string $reason)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $sign = $this->amount >= 0 ? '+' : '';

        return [
            'title' => 'Số dư ví thay đổi',
            'message' => 'Quản trị viên đã điều chỉnh ' . $sign . number_format($this->amount, 0, ',', '.') . ' ₫. Lý do: ' . $this->reason,
            'url' => route('panel.wallet.index'),
        ];
    }
}
