<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\OrderSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncOrderStatusJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $orderId)
    {
    }

    public function handle(OrderSyncService $sync): void
    {
        $order = Order::find($this->orderId);

        if ($order !== null) {
            $sync->syncOrder($order);
        }
    }
}
