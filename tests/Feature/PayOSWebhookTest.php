<?php

namespace Tests\Feature;

use App\Models\DepositRequest;
use App\Models\PayOsTransaction;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayOSWebhookTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $checksum = 'payos-checksum-test-key';

    protected function setUp(): void
    {
        parent::setUp();

        $s = app(SettingService::class);
        $s->set('min_deposit', '10000');
        $s->set('payos_enabled', '1');
        $s->setSecret('payos_client_id', 'client-id-test');
        $s->setSecret('payos_api_key', 'api-key-test');
        $s->setSecret('payos_checksum_key', $this->checksum);

        $this->user = User::factory()->create(['username' => 'payosuser', 'balance' => 0]);
    }

    /** Ký payload đúng như PayOS: ksort(data) rồi hash_hmac SHA256 với checksum key. */
    private function signed(array $data, string $code = '00'): array
    {
        ksort($data);
        $signature = hash_hmac('sha256', implode('|', array_map(
            fn ($v) => is_bool($v) ? ($v ? 'true' : 'false') : (string) $v,
            $data
        )), $this->checksum);

        return ['code' => $code, 'desc' => 'success', 'data' => $data, 'signature' => $signature];
    }


    private function makeDeposit(int $orderCode, int $amount = 50000): DepositRequest
    {
        $deposit = DepositRequest::create([
            'user_id' => $this->user->id,
            'amount' => $amount,
            'method' => 'payos',
            'transfer_code' => 'NHOSUB PAYOSUSER',
            'status' => 'pending',
            'created_at' => now(),
        ]);

        PayOsTransaction::create([
            'deposit_request_id' => $deposit->id,
            'user_id' => $this->user->id,
            'order_code' => $orderCode,
            'status' => 'pending',
        ]);

        return $deposit;
    }

    public function test_webhook_payos_hop_le_cong_tien(): void
    {
        $deposit = $this->makeDeposit(123456);

        $payload = $this->signed([
            'orderCode' => 123456,
            'amount' => 50000,
            'description' => 'NHOSUB PAYOSUSER',
            'reference' => 'FT-PAYOS-001',
            'paymentLinkId' => 'pl-001',
        ]);

        $this->postJson('/webhook/payos', $payload)->assertOk();

        $this->assertSame(50000, $this->user->fresh()->balance);
        $this->assertSame(1, WalletTransaction::where('type', 'deposit')->count());
        $this->assertSame('paid', PayOsTransaction::first()->status);
        $this->assertSame('approved', $deposit->fresh()->status);
    }

    public function test_webhook_payos_gui_lai_khong_cong_trung(): void
    {
        $this->makeDeposit(777);

        $payload = $this->signed([
            'orderCode' => 777,
            'amount' => 50000,
            'reference' => 'REF-777',
        ]);

        $this->postJson('/webhook/payos', $payload)->assertOk();
        $this->postJson('/webhook/payos', $payload)->assertOk();

        $this->assertSame(50000, $this->user->fresh()->balance);
        $this->assertSame(1, WalletTransaction::count());
    }

    public function test_chu_ky_sai_bi_tu_choi(): void
    {
        $this->makeDeposit(888);

        $payload = [
            'code' => '00',
            'data' => ['orderCode' => 888, 'amount' => 50000, 'reference' => 'REF-888'],
            'signature' => 'chu-ky-gia-mao',
        ];

        $this->postJson('/webhook/payos', $payload)->assertStatus(401);

        $this->assertSame(0, $this->user->fresh()->balance);
        $this->assertSame(0, WalletTransaction::count());
    }

    /** C1: orderCode lạ KHÔNG được cộng tiền theo description do attacker chọn. */
    public function test_order_code_la_khong_cong_tien_chi_ghi_log(): void
    {
        $payload = $this->signed([
            'orderCode' => 999999,
            'amount' => 1000000,
            'description' => 'NHOSUB ' . strtoupper($this->user->username),
            'reference' => 'FT-ATTACK',
        ]);

        $this->postJson('/webhook/payos', $payload)->assertOk();

        $this->assertSame(0, $this->user->fresh()->balance, 'Không được cộng tiền cho orderCode lạ');
        $this->assertSame(0, WalletTransaction::count());
        $this->assertSame('unmatched', \App\Models\WebhookLog::first()->status);
    }

    /** C1: số tiền PayOS báo lệch khỏi yêu cầu nạp nội bộ → không cộng, ghi log. */
    public function test_so_tien_lech_khoi_yeu_cau_khong_cong(): void
    {
        $this->makeDeposit(555, 50000);

        $payload = $this->signed([
            'orderCode' => 555,
            'amount' => 5, // PayOS báo 5₫ thay vì 50.000₫
            'description' => 'NHOSUB ' . strtoupper($this->user->username),
            'reference' => 'FT-555',
        ]);

        $this->postJson('/webhook/payos', $payload)->assertOk();

        $this->assertSame(0, $this->user->fresh()->balance, 'Số tiền lệch không được cộng');
        $this->assertSame(0, WalletTransaction::count());
    }
}
