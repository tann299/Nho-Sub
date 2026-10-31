<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DepositApproved extends Notification
{
    use Queueable;

    public function __construct(private readonly int $amount, private readonly int $depositId)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Nạp tiền thành công',
            'message' => 'Đã cộng ' . number_format($this->amount, 0, ',', '.') . ' ₫ vào ví (yêu cầu #' . $this->depositId . ').',
            'url' => route('panel.wallet.index'),
        ];
    }
}
