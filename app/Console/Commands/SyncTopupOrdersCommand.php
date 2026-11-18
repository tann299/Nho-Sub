<?php

namespace App\Console\Commands;

use App\Models\TopupOrder;
use App\Services\Napxu\TopupOrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncTopupOrdersCommand extends Command
{
    protected $signature = 'topup:sync {--limit=100}';
    protected $description = 'Đồng bộ đơn nạp xu Napxu và đối soát đơn gửi bị kẹt';

    public function handle(TopupOrderService $service): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $stuck = TopupOrder::where('status', TopupOrder::STATUS_CHARGED)
            ->where('created_at', '<', now()->subMinutes(10))
            ->update(['status' => TopupOrder::STATUS_STUCK_REVIEW,
                'error_message' => 'Chưa lưu được biên nhận Napxu. Đối soát trước khi hoàn tiền.']);
        $orders = TopupOrder::whereIn('status', TopupOrder::OPEN_STATUSES)
            ->whereNotNull('napxu_order_id')->orderByRaw('COALESCE(synced_at, created_at)')->limit($limit)->get();
        $synced = $failed = 0;
        foreach ($orders as $order) {
            try {
                $service->syncOne($order);
                $synced++;
            } catch (\Throwable) {
                // Không ghi payload/exception: có thể chứa cookie/PIN/key từ upstream.
                Log::warning('napxu.topup_sync_failed', ['topup_order_id' => $order->id]);
                $failed++;
            }
        }
        $this->info("Nạp xu: {$synced} đồng bộ, {$failed} lỗi, {$stuck} chờ đối soát.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
