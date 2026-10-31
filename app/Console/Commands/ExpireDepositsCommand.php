<?php

namespace App\Console\Commands;

use App\Services\DepositService;
use Illuminate\Console\Command;

class ExpireDepositsCommand extends Command
{
    protected $signature = 'deposits:expire';
    protected $description = 'Đóng các yêu cầu nạp qua cổng thanh toán đã quá hạn (30 phút)';

    public function handle(DepositService $deposits): int
    {
        $count = $deposits->expireStaleRequests();

        $this->info("Đã đóng {$count} yêu cầu nạp quá hạn.");

        return self::SUCCESS;
    }
}
