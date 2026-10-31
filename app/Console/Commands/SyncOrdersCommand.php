<?php

namespace App\Console\Commands;

use App\Services\OrderSyncService;
use Illuminate\Console\Command;

class SyncOrdersCommand extends Command
{
    protected $signature = 'orders:sync {--limit=100}';
    protected $description = 'Đồng bộ trạng thái đơn đang chạy từ nhà cung cấp';

    public function handle(OrderSyncService $sync): int
    {
        $stuck = $sync->flagStuckOrders();
        $result = $sync->syncBatch((int) $this->option('limit'));

        $this->info("Đồng bộ: {$result['synced']} đơn thành công, {$result['failed']} lỗi, {$stuck} đơn kẹt chuyển admin.");

        return self::SUCCESS;
    }
}
