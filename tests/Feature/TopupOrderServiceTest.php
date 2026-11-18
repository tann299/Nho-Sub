<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\NapxuException;
use App\Models\TikTokAccount;
use App\Models\TopupOrder;
use App\Models\TopupPackage;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Napxu\NapxuClient;
use App\Services\Napxu\TopupOrderService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TopupOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private TikTokAccount $account;
    private TopupPackage $package;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->user = User::factory()->create();
        app(WalletService::class)->apply($this->user->id, WalletTransaction::TYPE_DEPOSIT, 500000, 'test-opening');
        $this->account = TikTokAccount::create(['user_id' => $this->user->id, 'napxu_account_id' => 77, 'username' => '@tester']);
        $this->package = TopupPackage::create(['name' => '1000 xu', 'coins' => 1000, 'cost_price' => 278000,
            'sell_price' => 300000, 'server' => 'server6', 'napxu_package_id' => 5, 'is_active' => true]);
    }

    private function service(): TopupOrderService
    {
        return new TopupOrderService(app(WalletService::class), new NapxuClient('napx_test_secret'));
    }

    private function accepted(): array
    {
        return ['message' => 'Đơn hàng đã được tạo thành công.', 'order' => ['id' => 10, 'order_ref' => 'TU-10',
            'status' => 'pending', 'coins' => 1000, 'amount_vnd' => 278000], 'newBalance' => 722000];
    }

    private function place(?string $token = null): TopupOrder
    {
        return $this->service()->placeOrder($this->user, $this->account, $this->package, $token ?? (string) Str::uuid());
    }

    public function test_create_debits_wallet_once_and_sends_frozen_package(): void
    {
        Http::fake(['napxu.vn/api/topup/orders' => Http::response($this->accepted())]);
        $order = $this->place();
        $this->assertSame(200000, $this->user->fresh()->balance);
        $this->assertSame(10, $order->napxu_order_id);
        $this->assertSame('TU-10', $order->napxu_order_ref);
        $this->assertSame('pending', $order->status);
        Http::assertSent(fn ($r) => $r->data() === ['tiktok_account_id' => 77, 'package_id' => 5,
            'coins' => 1000, 'server' => 'server6']);
    }

    public function test_duplicate_client_token_returns_same_order_without_http_or_debit(): void
    {
        Http::fake(['napxu.vn/api/topup/orders' => Http::response($this->accepted())]);
        $token = (string) Str::uuid();
        $first = $this->place($token);
        $second = $this->place($token);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, TopupOrder::count());
        $this->assertSame(200000, $this->user->fresh()->balance);
        Http::assertSentCount(1);
    }

    public function test_definitive_rejection_refunds_and_records_failure(): void
    {
        Http::fake(['napxu.vn/api/topup/orders' => Http::response(['error' => 'Số dư Napxu không đủ.'], 400)]);
        $order = $this->place();
        $this->assertSame('failed', $order->status);
        $this->assertSame(300000, $order->refunded_amount);
        $this->assertSame(500000, $this->user->fresh()->balance);
        $this->assertSame(1, WalletTransaction::where('idempotency_key', "topup:{$order->id}:refund")->count());
    }

    public function test_timeout_keeps_debit_for_reconciliation_and_never_retries(): void
    {
        Http::fake(['napxu.vn/api/topup/orders' => Http::failedConnection()]);
        $token = (string) Str::uuid();
        $order = $this->place($token);
        $this->assertSame('stuck_review', $order->status);
        $this->assertSame(0, $order->refunded_amount);
        $this->assertSame(200000, $this->user->fresh()->balance);
        $this->assertSame($order->id, $this->place($token)->id);
        $this->assertSame(1, TopupOrder::count());
    }

    public function test_malformed_success_does_not_refund_or_retry(): void
    {
        Http::fake(['napxu.vn/api/topup/orders' => Http::response(['message' => 'Created', 'order' => []])]);
        $order = $this->place();
        $this->assertSame('stuck_review', $order->status);
        $this->assertSame(200000, $this->user->fresh()->balance);
        Http::assertSentCount(1);
    }

    public function test_insufficient_wallet_rolls_back_before_provider_call(): void
    {
        $this->package->update(['sell_price' => 700000]);
        Http::fake();
        try {
            $this->place();
            $this->fail('Expected insufficient wallet');
        } catch (InsufficientBalanceException) {
            $this->assertSame(0, TopupOrder::count());
            $this->assertSame(500000, $this->user->fresh()->balance);
        }
        Http::assertNothingSent();
    }

    public function test_customer_cannot_use_another_customers_account(): void
    {
        $other = User::factory()->create();
        $this->account->update(['user_id' => $other->id]);
        Http::fake();
        $this->expectException(ValidationException::class);
        $this->place();
    }

    public function test_inactive_package_cannot_be_bought(): void
    {
        $this->package->update(['is_active' => false]);
        Http::fake();
        $this->expectException(ValidationException::class);
        $this->place();
    }

    public function test_price_change_does_not_mutate_order_snapshots(): void
    {
        Http::fake(['napxu.vn/api/topup/orders' => Http::response($this->accepted())]);
        $order = $this->place();
        $this->package->update(['coins' => 2000, 'sell_price' => 600000, 'server' => 'server1', 'napxu_package_id' => 8]);
        $this->assertSame(300000, $order->fresh()->sell_price);
        $this->assertSame(1000, $order->fresh()->coins);
        $this->assertSame('server6', $order->fresh()->server);
        $this->assertSame(5, $order->fresh()->napxu_package_id);
    }

    public function test_sync_fetches_numeric_id_and_completes_without_refund(): void
    {
        Http::fake([
            'napxu.vn/api/topup/orders' => Http::response($this->accepted()),
            'napxu.vn/api/topup/orders/10' => Http::response(['order' => ['id' => 10, 'order_ref' => 'TU-10', 'coins' => 1000, 'status' => 'success']]),
        ]);
        $order = $this->place();
        $this->service()->syncOne($order);
        $this->assertSame('success', $order->fresh()->status);
        $this->assertSame(200000, $this->user->fresh()->balance);
        Http::assertSent(fn ($r) => $r->url() === 'https://napxu.vn/api/topup/orders/10' && $r->method() === 'GET');
    }

    public function test_auto_and_manual_refund_share_cap_and_key(): void
    {
        Http::fake([
            'napxu.vn/api/topup/orders' => Http::response($this->accepted()),
            'napxu.vn/api/topup/orders/10' => Http::response(['order' => ['id' => 10, 'order_ref' => 'TU-10', 'coins' => 1000, 'status' => 'refund']]),
        ]);
        $order = $this->place();
        $this->service()->syncOne($order);
        $this->service()->manualRefund($order->fresh());
        $this->service()->syncOne($order);
        $this->assertSame(500000, $this->user->fresh()->balance);
        $this->assertSame(300000, $order->fresh()->refunded_amount);
        $this->assertSame(1, WalletTransaction::where('idempotency_key', "topup:{$order->id}:refund")->count());
    }

    public function test_provider_down_during_sync_leaves_wallet_and_order_unchanged(): void
    {
        Http::fake([
            'napxu.vn/api/topup/orders' => Http::response($this->accepted()),
            'napxu.vn/api/topup/orders/10' => Http::failedConnection(),
        ]);
        $order = $this->place();
        try {
            $this->service()->syncOne($order);
            $this->fail('Expected connection failure');
        } catch (NapxuException) {
            $this->assertSame('pending', $order->fresh()->status);
            $this->assertSame(200000, $this->user->fresh()->balance);
        }
    }

    public function test_successful_order_cannot_be_manually_refunded(): void
    {
        Http::fake(['napxu.vn/api/topup/orders' => Http::response($this->accepted())]);
        $order = $this->place();
        $order->update(['status' => 'success']);
        $this->expectException(ValidationException::class);
        $this->service()->manualRefund($order);
    }

    public function test_unresolved_order_cannot_be_canceled_to_get_free_coins(): void
    {
        Http::fake(['napxu.vn/api/topup/orders' => Http::failedConnection()]);
        $order = $this->place();
        $this->expectException(ValidationException::class);
        $this->service()->cancelPending($order);
    }

    public function test_cancellation_refunds_only_after_confirmed_remote_state(): void
    {
        Http::fake([
            'napxu.vn/api/topup/orders' => Http::response($this->accepted()),
            'napxu.vn/api/topup/orders/10' => Http::response(['order' => ['id' => 10, 'order_ref' => 'TU-10', 'coins' => 1000, 'status' => 'refund']]),
            'napxu.vn/api/topup/orders/10/cancel' => Http::response(['message' => 'Đơn đã hủy, tiền đã hoàn về ví.']),
        ]);
        $order = $this->place();
        $this->service()->cancelPending($order);
        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertSame(500000, $this->user->fresh()->balance);
    }
}
