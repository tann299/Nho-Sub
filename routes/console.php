<?php

use Illuminate\Support\Facades\Schedule;

// Đồng bộ trạng thái đơn mỗi 5 phút — SRS yêu cầu.
Schedule::command('orders:sync')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('topup:sync')->everyFiveMinutes()->withoutOverlapping();

// Đóng yêu cầu nạp quá hạn.
Schedule::command('deposits:expire')->everyFiveMinutes()->withoutOverlapping();

// Xử lý job trong queue database (không dùng Redis/Supervisor).
Schedule::command('queue:work --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();
