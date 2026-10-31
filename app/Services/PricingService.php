<?php

namespace App\Services;

/**
 * Tính giá — toàn bộ phép tính tiền chạy trên SỐ NGUYÊN VND.
 *
 * Công thức (spec 5.7):
 *   cost_price  = floor(cost_usd * tỉ_giá)
 *   sell_price  = round(cost_price * (1 + %/100))
 *   total       = floor(sell_price * quantity / 1000)
 *   cost_amount = floor(cost_price * quantity / 1000)
 *
 * Không dùng float cho kết quả cuối — ép int ngay sau mỗi bước.
 */
class PricingService
{
    public function __construct(private readonly SettingService $settings)
    {
    }

    public function exchangeRate(): int
    {
        return max(1, $this->settings->getInt('exchange_rate', 26000));
    }

    public function markupPercent(): string
    {
        return $this->settings->getDecimal('default_markup_percent', '30');
    }

    public function costPriceFromUsd(string|float $usd): int
    {
        $rate = $this->exchangeRate();
        $value = (float) $usd;

        if ($value <= 0) {
            return 0;
        }

        // Tính bằng số nguyên micro-USD để tránh sai số nhị phân:
        // 0.080000 → 80000 micro-USD; 80000 * 26000 / 1_000_000 = 2080.
        $micro = (int) round($value * 1_000_000);

        return intdiv($micro * $rate, 1_000_000);
    }

    public function sellPriceFromCost(int $costPrice): int
    {
        if ($costPrice <= 0) {
            return 0;
        }

        $percent = (float) $this->markupPercent();
        $percent = max(0.0, $percent);

        // Nhân phần trăm bằng số nguyên phần vạn để tránh float:
        // 30.00% → 3000 phần vạn; 2080 * (10000 + 3000) / 10000 = 2704.
        $bps = (int) round($percent * 100);

        return intdiv($costPrice * (10_000 + $bps) + 5_000, 10_000);
    }

    /** Tổng tiền khách trả, làm tròn xuống, đơn vị VND. */
    public function orderTotal(int $unitPrice, int $quantity): int
    {
        if ($unitPrice <= 0 || $quantity <= 0) {
            return 0;
        }

        return intdiv($unitPrice * $quantity, 1000);
    }

    /** Chi phí gốc phải trả nhà cung cấp, đơn vị VND. */
    public function orderCost(int $costPrice, int $quantity): int
    {
        if ($costPrice <= 0 || $quantity <= 0) {
            return 0;
        }

        return intdiv($costPrice * $quantity, 1000);
    }
}
