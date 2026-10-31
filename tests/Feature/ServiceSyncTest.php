<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Service;
use App\Services\ServiceSyncService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ServiceSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $s = app(SettingService::class);
        $s->set('exchange_rate', '26000');
        $s->set('default_markup_percent', '30');
        $s->setSecret('topsubre_api_key', 'real-key');
        $s->set('demo_mode', 'off');
    }

    private function fakeServices(array $rows): void
    {
        Http::fake(['likenhanh.pro/*' => Http::response($rows, 200)]);
    }

    public function test_tao_dich_vu_moi_va_danh_muc_tu_dong(): void
    {
        $this->fakeServices([
            ['service' => 101, 'name' => 'Like FB', 'category' => 'Facebook Likes',
             'rate' => '0.08', 'min' => 100, 'max' => 50000, 'description' => 'Mô tả'],
            ['service' => 102, 'name' => 'View TikTok', 'category' => 'TikTok Views',
             'rate' => '0.01', 'min' => 1000, 'max' => 100000, 'description' => ''],
        ]);

        $result = app(ServiceSyncService::class)->sync();

        $this->assertSame(2, $result['created']);
        $this->assertSame(2, Service::count());
        $this->assertGreaterThanOrEqual(1, Category::count());

        $svc = Service::where('provider_service_id', '101')->first();
        $this->assertSame('Like FB', $svc->name);
        $this->assertSame(2080, $svc->cost_price);       // 0.08 * 26000
        $this->assertSame(2704, $svc->sell_price);       // +30%
        $this->assertSame('0.080000', $svc->cost_usd);
        $this->assertSame(100, $svc->min_qty);
        $this->assertSame(50000, $svc->max_qty);
    }

    public function test_cap_nhat_gia_goc_giu_gia_ban_dat_rieng(): void
    {
        $cat = Category::factory()->create(['name' => 'Facebook Likes', 'platform' => 'facebook']);
        $svc = Service::factory()->create([
            'category_id' => $cat->id,
            'provider_service_id' => '101',
            'cost_usd' => '0.05',
            'cost_price' => 1300,
            'sell_price' => 9999,
            'sell_price_override' => true,
        ]);

        $this->fakeServices([
            ['service' => 101, 'name' => 'Like FB', 'category' => 'Facebook Likes',
             'rate' => '0.10', 'min' => 100, 'max' => 50000],
        ]);

        $result = app(ServiceSyncService::class)->sync();

        $this->assertSame(1, $result['updated']);
        $svc->refresh();
        $this->assertSame(2600, $svc->cost_price);       // cập nhật
        $this->assertSame(9999, $svc->sell_price);       // GIỮ NGUYÊN
        $this->assertTrue($svc->sell_price_override);
    }

    public function test_cap_nhat_gia_ban_khi_khong_co_override(): void
    {
        $cat = Category::factory()->create(['name' => 'Facebook Likes', 'platform' => 'facebook']);
        $svc = Service::factory()->create([
            'category_id' => $cat->id,
            'provider_service_id' => '101',
            'cost_price' => 1300,
            'sell_price' => 1690,
            'sell_price_override' => false,
        ]);

        $this->fakeServices([
            ['service' => 101, 'name' => 'Like FB', 'category' => 'Facebook Likes',
             'rate' => '0.10', 'min' => 100, 'max' => 50000],
        ]);

        app(ServiceSyncService::class)->sync();

        $svc->refresh();
        $this->assertSame(2600, $svc->cost_price);
        $this->assertSame(3380, $svc->sell_price);       // 2600 * 1.3
    }

    public function test_dich_vu_bien_mat_bi_an_khong_xoa(): void
    {
        $cat = Category::factory()->create(['name' => 'Facebook Likes', 'platform' => 'facebook']);
        $svc = Service::factory()->create([
            'category_id' => $cat->id,
            'provider_service_id' => '999',
            'is_active' => true,
        ]);

        $this->fakeServices([
            ['service' => 101, 'name' => 'Like FB', 'category' => 'Facebook Likes',
             'rate' => '0.08', 'min' => 100, 'max' => 50000],
        ]);

        $result = app(ServiceSyncService::class)->sync();

        $this->assertSame(1, $result['deactivated']);
        $this->assertSame(2, Service::count(), 'Không được xoá dịch vụ');
        $this->assertFalse($svc->fresh()->is_active);
    }

    public function test_api_loi_khong_xoa_du_lieu_cu(): void
    {
        $cat = Category::factory()->create();
        Service::factory()->create(['category_id' => $cat->id, 'provider_service_id' => '101']);

        Http::fake(['likenhanh.pro/*' => Http::response(['error' => 'Server error'], 200)]);

        try {
            app(ServiceSyncService::class)->sync();
            $this->fail('Phải ném ProviderException');
        } catch (\App\Exceptions\ProviderException $e) {
            $this->assertSame(1, Service::count());
        }
    }
}
