<?php

namespace Tests\Feature;

use App\Models\TikTokAccount;
use App\Models\TopupOrder;
use App\Models\TopupPackage;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\SettingService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TopupSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    private function order(int $remoteId, string $status = 'pending'): TopupOrder
    {
        $user = User::factory()->create();
        $account = TikTokAccount::create(['user_id' => $user->id, 'napxu_account_id' => $remoteId + 100, 'username' => '@tester']);
        $package = TopupPackage::create(['name' => '100 xu', 'coins' => 100, 'cost_price' => 27800,
            'sell_price' => 30000, 'server' => 'server1', 'napxu_package_id' => 5]);
        $order = TopupOrder::create(['user_id' => $user->id, 'tiktok_account_id' => $account->id,
            'topup_package_id' => $package->id, 'coins' => 100, 'cost_price' => 27800, 'sell_price' => 30000,
            'napxu_order_id' => $remoteId, 'napxu_order_ref' => 'TU-'.$remoteId, 'status' => $status]);
        app(WalletService::class)->apply($user->id, WalletTransaction::TYPE_DEPOSIT, 50000, 'open-'.$user->id);
        app(WalletService::class)->apply($user->id, WalletTransaction::TYPE_ORDER_CHARGE, -30000, "topup:{$order->id}:charge");

        return $order;
    }

    public function test_sync_completes_and_refunds_both_with_same_cron(): void
    {
        app(SettingService::class)->setSecret('napxu_api_key', 'napx_test_secret');
        $completed = $this->order(10);
        $refunded = $this->order(11);
        Http::fake([
            'napxu.vn/api/topup/orders/10' => Http::response(['order' => ['id' => 10, 'order_ref' => 'TU-10', 'status' => 'success', 'coins' => 100]]),
            'napxu.vn/api/topup/orders/11' => Http::response(['order' => ['id' => 11, 'order_ref' => 'TU-11', 'status' => 'refund', 'coins' => 100]]),
        ]);
        $this->artisan('topup:sync')->assertSuccessful();
        $this->assertSame('success', $completed->fresh()->status);
        $this->assertSame('refunded', $refunded->fresh()->status);
        $this->assertSame(20000, $completed->user->balance);
        $this->assertSame(50000, $refunded->user->balance);
    }

    public function test_api_failure_keeps_wallet_and_status(): void
    {
        app(SettingService::class)->setSecret('napxu_api_key', 'napx_test_secret');
        $order = $this->order(12);
        Http::fake(['napxu.vn/*' => Http::failedConnection()]);
        $this->artisan('topup:sync')->assertExitCode(1);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(20000, $order->user->balance);
    }

    public function test_stale_charged_order_requires_review_not_refund(): void
    {
        $order = $this->order(13, 'charged');
        $order->forceFill(['napxu_order_id' => null, 'napxu_order_ref' => null, 'created_at' => now()->subMinutes(15)])->save();
        Http::fake();
        $this->artisan('topup:sync')->assertSuccessful();
        $this->assertSame('stuck_review', $order->fresh()->status);
        $this->assertSame(20000, $order->user->balance);
        Http::assertNothingSent();
    }
}
