<?php

namespace App\Providers;

use App\Providers\Dto\ProviderBalance;
use App\Providers\Dto\ProviderOrderResult;
use App\Providers\Dto\ProviderOrderStatus;
use App\Providers\Dto\ProviderService;

/**
 * Hợp đồng chung cho mọi nhà cung cấp SMM.
 * Luồng đặt đơn chỉ làm việc với interface này → thêm NCC mới không phải sửa code đặt đơn.
 */
interface ProviderInterface
{
    public function balance(): ProviderBalance;

    /** @return ProviderService[] */
    public function services(): array;

    public function addOrder(string $serviceId, string $link, int $quantity, ?string $reference = null): ProviderOrderResult;

    public function orderStatus(string $providerOrderId): ProviderOrderStatus;
}
