<?php

namespace Tests\Feature;

use App\Models\DepositRequest;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WebhookLog;
use App\Services\DepositService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SePayWebhookTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $apiKey = 'sepay-test-key-123';

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingService::class)->setSecret('sepay_api_key', $this->apiKey);
        app(SettingService::class)->set('min_deposit', '10000');

        $this->user = User::factory()->create(['username' => 'khachvip', 'balance' => 0]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'id' => 99881,
            'gateway' => 'MB Bank',
            'transactionDate' => '2026-10-31 10:00:00',
            'accountNumber' => '0123456789',
            'content' => 'NHOSUB KHACHVIP chuyen tien',
            'transferType' => 'in',
            'transferAmount' => 100000,
            'referenceCode' => 'FT26100001',
            'description' => '',
        ], $overrides);
    }

    private function postWebhook(array $payload, ?string $authHeader = null)
    {
        return $this->withHeaders([
            'Authorization' => $authHeader ?? 'Apikey ' . $this->apiKey,
        ])->postJson('/webhook/sepay', $payload);
    }

    public function test_webhook_hop_le_cong_tien_dung(): void
    {
        $this->postWebhook($this->payload())->assertOk()->assertJson(['success' => true]);

        $this->assertSame(100000, $this->user->fresh()->balance);
        $this->assertSame(1, WalletTransaction::where('type', 'deposit')->count());
        $this->assertSame(WebhookLog::STATUS_PROCESSED, WebhookLog::first()->status);
        $this->assertSame(1, DepositRequest::where('status', 'approved')->count());
    }

    public function test_webhook_gui_lai_cung_ma_giao_dich_khong_cong_trung(): void
    {
        $this->postWebhook($this->payload())->assertOk();
        $this->postWebhook($this->payload())->assertOk();
        $this->postWebhook($this->payload())->assertOk();

        $this->assertSame(100000, $this->user->fresh()->balance);
        $this->assertSame(1, WalletTransaction::count());
        $this->assertSame(1, WebhookLog::count());
    }

    public function test_sai_apikey_bi_tu_choi_401(): void
    {
        $this->postWebhook($this->payload(), 'Apikey sai-key')->assertStatus(401);

        $this->assertSame(0, $this->user->fresh()->balance);
        $this->assertSame(0, WalletTransaction::count());
    }

    public function test_thieu_header_authorization_bi_tu_choi(): void
    {
        $this->postJson('/webhook/sepay', $this->payload())->assertStatus(401);
        $this->assertSame(0, $this->user->fresh()->balance);
    }

    public function test_tien_ra_khong_xu_ly(): void
    {
        $this->postWebhook($this->payload(['transferType' => 'out']))->assertOk();

        $this->assertSame(0, $this->user->fresh()->balance);
        $this->assertSame(WebhookLog::STATUS_REJECTED, WebhookLog::first()->status);
    }

    public function test_so_tien_duoi_muc_toi_thieu_bi_tu_choi(): void
    {
        $this->postWebhook($this->payload(['transferAmount' => 5000]))->assertOk();

        $this->assertSame(0, $this->user->fresh()->balance);
        $this->assertSame(WebhookLog::STATUS_REJECTED, WebhookLog::first()->status);
        $this->assertStringContainsString('10.000', WebhookLog::first()->reason);
    }

    public function test_noi_dung_khong_khop_user_ghi_unmatched(): void
    {
        $this->postWebhook($this->payload(['content' => 'chuyen tien mua hang']))->assertOk();

        $this->assertSame(0, $this->user->fresh()->balance);
        $this->assertSame(WebhookLog::STATUS_UNMATCHED, WebhookLog::first()->status);
    }

    public function test_username_khong_ton_tai_ghi_unmatched(): void
    {
        $this->postWebhook($this->payload(['content' => 'NHOSUB NGUOIKHONGCO']))->assertOk();

        $this->assertSame(WebhookLog::STATUS_UNMATCHED, WebhookLog::first()->status);
    }

    public function test_parse_chap_nhan_ba_bien_the(): void
    {
        $svc = app(DepositService::class);

        $this->assertSame('khachvip', $svc->parseTransferContent('NHOSUB KHACHVIP'));
        $this->assertSame('khachvip', $svc->parseTransferContent('NHOSUBKHACHVIP'));
        $this->assertSame('khachvip', $svc->parseTransferContent('nhosub  khachvip chuyen tien'));
        $this->assertSame('khachvip', $svc->parseTransferContent('MBVCB.123.NHOSUB KHACHVIP.CT tu 0123'));
        $this->assertNull($svc->parseTransferContent('NHOSUB'));
        $this->assertNull($svc->parseTransferContent('khong co gi'));
    }

    public function test_username_la_tien_to_cua_username_khac_van_khop_dung(): void
    {
        $khachNgan = User::factory()->create(['username' => 'khach', 'balance' => 0]);

        $this->postWebhook($this->payload(['content' => 'NHOSUB KHACHVIP nap tien']))->assertOk();

        $this->assertSame(100000, $this->user->fresh()->balance);
        $this->assertSame(0, $khachNgan->fresh()->balance);
    }

    public function test_payload_bi_che_key_truoc_khi_luu(): void
    {
        $this->postWebhook($this->payload())->assertOk();

        $log = WebhookLog::first();
        $json = json_encode($log->payload, JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString($this->apiKey, $json);
    }

    public function test_so_du_va_so_cai_khop_nhau(): void
    {
        $this->postWebhook($this->payload(['id' => 111]))->assertOk();
        $this->postWebhook($this->payload(['id' => 222, 'transferAmount' => 50000]))->assertOk();

        $tong = (int) WalletTransaction::where('user_id', $this->user->id)->sum('amount');

        $this->assertSame(150000, $tong);
        $this->assertSame(150000, $this->user->fresh()->balance);
    }
}
