<?php

namespace Tests\Feature;

use App\Models\TikTokAccount;
use App\Models\TopupOrder;
use App\Models\TopupPackage;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminTopupTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        Http::preventStrayRequests();
    }

    private function data(): array
    {
        return ['name' => 'Gói 1000 xu', 'coins' => 1000, 'cost_price' => 278000,
            'sell_price' => 300000, 'server' => 'server6', 'napxu_package_id' => 5, 'is_active' => 1];
    }

    public function test_admin_creates_updates_and_toggles_packages(): void
    {
        $this->actingAs($this->admin)->post('/admin/topup-packages', $this->data())->assertRedirect('/admin/topup-packages');
        $package = TopupPackage::firstOrFail();
        $this->get('/admin/topup-packages')->assertOk()->assertSee('Gói 1000 xu');
        $this->post('/admin/topup-packages/'.$package->id, array_merge($this->data(), ['sell_price' => 310000]))->assertRedirect();
        $this->assertSame(310000, $package->fresh()->sell_price);
        $this->post('/admin/topup-packages/'.$package->id.'/toggle')->assertRedirect();
        $this->assertFalse($package->fresh()->is_active);
    }

    public function test_customer_cannot_access_admin_topup(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer)->get('/admin/topup-packages')->assertForbidden();
        $this->get('/admin/topup-orders')->assertForbidden();
        $this->post('/admin/topup-packages', $this->data())->assertForbidden();
    }

    public function test_packages_reject_invalid_prices_and_server(): void
    {
        $this->actingAs($this->admin)->post('/admin/topup-packages', array_merge($this->data(), [
            'sell_price' => 100, 'server' => '../../other', 'coins' => -5,
        ]))->assertSessionHasErrors(['sell_price', 'server', 'coins']);
        $this->assertSame(0, TopupPackage::count());
    }

    public function test_manual_refund_requires_confirmation_and_does_not_double_refund(): void
    {
        $customer = User::factory()->create();
        $account = TikTokAccount::create(['user_id' => $customer->id, 'napxu_account_id' => 77, 'username' => '@tester']);
        $package = TopupPackage::create($this->data());
        $order = TopupOrder::create(['user_id' => $customer->id, 'tiktok_account_id' => $account->id,
            'topup_package_id' => $package->id, 'coins' => 1000, 'cost_price' => 278000,
            'sell_price' => 300000, 'status' => 'stuck_review']);
        app(WalletService::class)->apply($customer->id, WalletTransaction::TYPE_DEPOSIT, 400000, 'fund-test');
        app(WalletService::class)->apply($customer->id, WalletTransaction::TYPE_ORDER_CHARGE, -300000, "topup:{$order->id}:charge");
        $this->actingAs($this->admin)->post('/admin/topup-orders/'.$order->id.'/refund')->assertSessionHasErrors('confirmed');
        $this->post('/admin/topup-orders/'.$order->id.'/refund', ['confirmed' => 1])->assertRedirect();
        $this->post('/admin/topup-orders/'.$order->id.'/refund', ['confirmed' => 1])->assertRedirect();
        $this->assertSame(400000, $customer->fresh()->balance);
        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertSame(1, WalletTransaction::where('idempotency_key', "topup:{$order->id}:refund")->count());
    }
}
