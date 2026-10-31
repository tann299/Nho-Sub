<?php

namespace Tests\Feature;

use App\Services\PricingService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingTest extends TestCase
{
    use RefreshDatabase;

    private PricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = app(SettingService::class);
        $settings->set('exchange_rate', '26000');
        $settings->set('default_markup_percent', '30');

        $this->pricing = app(PricingService::class);
    }

    public function test_quy_doi_usd_sang_vnd_theo_ti_gia(): void
    {
        $this->assertSame(2080, $this->pricing->costPriceFromUsd('0.080000'));
        $this->assertSame(130, $this->pricing->costPriceFromUsd('0.005000'));
        $this->assertSame(0, $this->pricing->costPriceFromUsd('0.000019'));
    }

    public function test_gia_ban_theo_phan_tram_loi_nhuan(): void
    {
        $this->assertSame(2704, $this->pricing->sellPriceFromCost(2080));
        $this->assertSame(13000, $this->pricing->sellPriceFromCost(10000));
    }

    public function test_tong_tien_don_lam_tron_xuong(): void
    {
        $this->assertSame(3380, $this->pricing->orderTotal(3380, 1000));
        $this->assertSame(1125, $this->pricing->orderTotal(3380, 333));
        $this->assertSame(4056, $this->pricing->orderTotal(2704, 1500));
    }

    public function test_so_luong_cuc_lon_khong_tran_so(): void
    {
        $this->assertSame(10_000_000, $this->pricing->orderTotal(1000, 10_000_000));
        $this->assertSame(5_000_000_000, $this->pricing->orderTotal(50000, 100_000_000));
    }

    public function test_doi_ti_gia_lam_doi_gia_quy_doi(): void
    {
        app(SettingService::class)->set('exchange_rate', '27000');

        $this->assertSame(2160, $this->pricing->costPriceFromUsd('0.080000'));
    }

    public function test_phan_tram_loi_nhuan_thap_nhat_la_0(): void
    {
        app(SettingService::class)->set('default_markup_percent', '0');

        $this->assertSame(2080, $this->pricing->sellPriceFromCost(2080));
    }
}
