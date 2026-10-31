<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Providers\Demo\DemoProvider;
use App\Providers\ProviderManager;
use App\Providers\TopSubRe\TopSubReProvider;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoProviderTest extends TestCase
{
    use RefreshDatabase;

    private function provider(): DemoProvider
    {
        return app(DemoProvider::class);
    }

    private function makeOrder(array $overrides = []): Order
    {
        $user = User::factory()->create();
        $service = Service::factory()->create(['provider_service_id' => 'demo-1']);

        return Order::factory()->create(array_merge([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'quantity' => 1000,
        ], $overrides));
    }

    public function test_demo_tra_ve_so_du_va_danh_sach_dich_vu(): void
    {
        $balance = $this->provider()->balance();
        $this->assertIsNumeric($balance->usd);

        $services = $this->provider()->services();
        $this->assertNotEmpty($services);
        $this->assertSame('demo-1', $services[0]->id);
    }

    public function test_add_order_tra_ma_gan_voi_don(): void
    {
        $order = $this->makeOrder();
        $result = $this->provider()->addOrder('demo-1', $order->target_link, 1000);

        $this->assertSame('demo:' . $order->id, $result->providerOrderId);
    }

    public function test_trang_thai_tien_hoa_theo_thoi_gian(): void
    {
        $order = $this->makeOrder();
        $order->forceFill(['created_at' => now()])->saveQuietly();
        $this->assertSame('pending', $this->provider()->orderStatus('demo:' . $order->id)->status);

        $order->forceFill(['created_at' => now()->subMinutes(2)])->saveQuietly();
        $this->assertSame('processing', $this->provider()->orderStatus('demo:' . $order->id)->status);

        $order->forceFill(['created_at' => now()->subMinutes(5)])->saveQuietly();
        $this->assertSame('completed', $this->provider()->orderStatus('demo:' . $order->id)->status);
    }

    public function test_che_do_partial_tra_remains(): void
    {
        app(SettingService::class)->set('demo_scenario', 'partial');
        $order = $this->makeOrder();
        $order->forceFill(['created_at' => now()->subMinutes(5)])->saveQuietly();

        $status = $this->provider()->orderStatus('demo:' . $order->id);

        $this->assertSame('partial', $status->status);
        $this->assertSame(400, $status->remains);
        $this->assertSame(0, $status->startCount);
    }

    public function test_che_do_cancel_tra_canceled(): void
    {
        app(SettingService::class)->set('demo_scenario', 'cancel');
        $order = $this->makeOrder();
        $order->forceFill(['created_at' => now()->subMinutes(5)])->saveQuietly();

        $status = $this->provider()->orderStatus('demo:' . $order->id);

        $this->assertSame('canceled', $status->status);
        $this->assertSame(1000, $status->remains);
    }

    public function test_manager_tra_ve_demo_khi_chua_co_api_key(): void
    {
        app(SettingService::class)->set('topsubre_api_key', '');

        $this->assertInstanceOf(DemoProvider::class, app(ProviderManager::class)->driver());
    }

    public function test_manager_tra_ve_topsubre_khi_co_api_key(): void
    {
        app(SettingService::class)->setSecret('topsubre_api_key', 'real-key-abcdef');
        app(SettingService::class)->set('demo_mode', 'off');

        $this->assertInstanceOf(
            TopSubReProvider::class,
            app(ProviderManager::class)->driver()
        );
    }

    public function test_manager_buoc_dung_demo_khi_bat_demo_mode(): void
    {
        app(SettingService::class)->setSecret('topsubre_api_key', 'real-key-abcdef');
        app(SettingService::class)->set('demo_mode', 'on');

        $this->assertInstanceOf(DemoProvider::class, app(ProviderManager::class)->driver());
    }
}
