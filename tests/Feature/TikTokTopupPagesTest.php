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
use Illuminate\Support\Str;
use Tests\TestCase;

class TikTokTopupPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        app(SettingService::class)->setSecret('napxu_api_key', 'napx_test_secret');
        Http::preventStrayRequests();
    }

    public function test_guest_cannot_access_topup_pages(): void
    {
        foreach (['/tiktok-accounts', '/topup', '/topup/orders'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
    }

    public function test_customer_has_separate_topup_navigation(): void
    {
        $this->actingAs($this->user)->get('/tiktok-accounts')->assertOk()->assertSee('TikTok của tôi');
        $this->get('/topup')->assertOk()->assertSee('Nạp xu TikTok')->assertSee('Chưa có gói nạp');
        $this->get('/topup/orders')->assertOk()->assertSee('Đơn nạp xu');
    }

    public function test_customer_can_order_using_nhosub_wallet(): void
    {
        $account = TikTokAccount::create(['user_id' => $this->user->id, 'napxu_account_id' => 77, 'username' => '@tester']);
        $package = TopupPackage::create(['name' => '1000 xu', 'coins' => 1000, 'cost_price' => 278000,
            'sell_price' => 300000, 'server' => 'server6', 'napxu_package_id' => 5, 'is_active' => true]);
        app(WalletService::class)->apply($this->user->id, WalletTransaction::TYPE_DEPOSIT, 400000, 'fund');
        Http::fake(['napxu.vn/api/topup/orders' => Http::response(['order' => ['id' => 10, 'order_ref' => 'TU-10',
            'status' => 'pending', 'coins' => 1000, 'amount_vnd' => 278000]])]);
        $token = (string) Str::uuid();
        $payload = ['tiktok_account_id' => $account->id, 'topup_package_id' => $package->id, 'client_token' => $token];
        $this->actingAs($this->user)->post('/topup', $payload)->assertRedirect('/topup/orders');
        $this->post('/topup', $payload)->assertRedirect('/topup/orders');
        $this->assertSame(100000, $this->user->fresh()->balance);
        $this->assertSame(1, TopupOrder::count());
        Http::assertSentCount(1);
    }

    public function test_customer_never_sees_others_accounts_or_orders(): void
    {
        $other = User::factory()->create();
        $account = TikTokAccount::create(['user_id' => $other->id, 'napxu_account_id' => 99, 'username' => '@secret_owner']);
        $package = TopupPackage::create(['name' => '100 xu', 'coins' => 100, 'cost_price' => 27800,
            'sell_price' => 30000, 'server' => 'server1', 'napxu_package_id' => 5]);
        $order = TopupOrder::create(['user_id' => $other->id, 'tiktok_account_id' => $account->id, 'topup_package_id' => $package->id,
            'coins' => 100, 'sell_price' => 30000, 'cost_price' => 27800, 'napxu_order_ref' => 'TU-SECRET']);
        $this->actingAs($this->user)->get('/tiktok-accounts')->assertOk()->assertDontSee('@secret_owner');
        $this->get('/topup/orders')->assertOk()->assertDontSee('TU-SECRET');
        $this->post('/tiktok-accounts/'.$account->id.'/refresh')->assertNotFound();
        $this->post('/tiktok-accounts/'.$account->id.'/delete')->assertNotFound();
        $this->post('/topup/orders/'.$order->id.'/refresh')->assertNotFound();
        $this->post('/topup', ['tiktok_account_id' => $account->id, 'topup_package_id' => $package->id,
            'client_token' => (string) Str::uuid()])->assertSessionHasErrors('tiktok_account_id');
    }

    public function test_cookie_validation_never_flashes_cookie_into_session(): void
    {
        $cookie = str_repeat('x', 21000);
        $this->actingAs($this->user)->from('/tiktok-accounts')->post('/tiktok-accounts/cookie', ['cookie' => $cookie])
            ->assertRedirect('/tiktok-accounts')->assertSessionHasErrors('cookie');
        $this->assertNull(session()->getOldInput('cookie'));
    }

    public function test_qr_session_cannot_be_polled_by_another_user(): void
    {
        $id = str_repeat('q', 64);
        Http::fake(['napxu.vn/api/auth/tiktok/qr/generate' => Http::response([
            'sessionId' => $id, 'qrImage' => 'data:image/png;base64,iVBORw0KGgo=', 'timeRemaining' => 180,
        ])]);
        $this->actingAs($this->user)->postJson('/tiktok-accounts/qr')->assertOk()->assertJsonPath('sessionId', $id);
        $this->actingAs(User::factory()->create())->getJson('/tiktok-accounts/qr/'.$id)->assertUnprocessable();
        Http::assertSentCount(1);
    }
}
