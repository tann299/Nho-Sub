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

/**
 * Mã xác thực nạp tiền: mỗi yêu cầu có mã riêng → webhook đối chiếu 1-1.
 */
class DepositVerificationCodeTest extends TestCase
{
    use RefreshDatabase;

    private DepositService $deposits;
    private string $apiKey = 'code-test-key';

    protected function setUp(): void
    {
        parent::setUp();

        $s = app(SettingService::class);
        $s->setSecret('sepay_api_key', $this->apiKey);
        $s->set('min_deposit', '10000');

        $this->deposits = app(DepositService::class);
    }

    public function test_sinh_ma_6_ky_tu_hop_le(): void
    {
        $code = $this->deposits->generateVerificationCode();

        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{6}$/', $code, 'Mã 6 ký tự, không có 0/O/1/I');
    }

    public function test_yeu_cau_duyet_tay_co_ma_xac_thuc(): void
    {
        $user = User::factory()->create(['balance' => 0]);

        $req = $this->deposits->createManualRequest($user->id, 50000);

        $this->assertNotNull($req->verification_code);
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{6}$/', $req->verification_code);
    }

    public function test_webhook_khop_ma_xac_thuc_cong_tien_va_duyet_yeu_cau(): void
    {
        $user = User::factory()->create(['username' => 'khachcode', 'balance' => 0]);
        $req = $this->deposits->createManualRequest($user->id, 50000);

        // Khách chuyển khoản kèm mã xác thực trong nội dung.
        $this->withHeaders(['Authorization' => 'Apikey ' . $this->apiKey])
            ->postJson('/webhook/sepay', [
                'id' => 889001,
                'transferType' => 'in',
                'transferAmount' => 50000,
                'content' => 'NHOSUB KHACHCODE ' . $req->verification_code,
                'referenceCode' => 'FT889001',
            ])->assertOk();

        $this->assertSame(50000, $user->fresh()->balance);
        $this->assertSame('approved', $req->fresh()->status, 'Yêu cầu duyệt tay được duyệt tự động');
        $this->assertSame(1, WalletTransaction::count());
    }

    public function test_ma_khong_khop_nhung_username_dung_van_cong_theo_username(): void
    {
        $user = User::factory()->create(['username' => 'khachold', 'balance' => 0]);

        $this->withHeaders(['Authorization' => 'Apikey ' . $this->apiKey])
            ->postJson('/webhook/sepay', [
                'id' => 889002,
                'transferType' => 'in',
                'transferAmount' => 50000,
                'content' => 'NHOSUB KHACHOLD ZZTOP9', // mã không tồn tại
                'referenceCode' => 'FT889002',
            ])->assertOk();

        // Fallback username vẫn cộng (cách cũ hoạt động).
        $this->assertSame(50000, $user->fresh()->balance);
    }

    public function test_ma_khong_khop_va_username_sai_ghi_unmatched(): void
    {
        // KHÔNG tạo user nào khớp nội dung — cả mã lẫn username đều lạ.
        $this->withHeaders(['Authorization' => 'Apikey ' . $this->apiKey])
            ->postJson('/webhook/sepay', [
                'id' => 889003,
                'transferType' => 'in',
                'transferAmount' => 50000,
                'content' => 'NHOSUB NGUOICO QQWERT', // mã lạ + username không tồn tại
                'referenceCode' => 'FT889003',
            ])->assertOk();

        $this->assertSame(WebhookLog::STATUS_UNMATCHED, WebhookLog::first()->status);
        $this->assertStringContainsString('QQWERT', (string) WebhookLog::first()->reason, 'Log ghi mã khách chuyển để admin đối chiếu');
        $this->assertSame('QQWERT', WebhookLog::first()->expected_code);
    }

    public function test_sai_so_tien_so_voi_ma_khong_cong_theo_ma(): void
    {
        $user = User::factory()->create(['username' => 'khachsosanh', 'balance' => 0]);
        $req = $this->deposits->createManualRequest($user->id, 100000);

        // Chuyển ít hơn yêu cầu nhiều (50k < 100k) → không khớp theo mã.
        $this->withHeaders(['Authorization' => 'Apikey ' . $this->apiKey])
            ->postJson('/webhook/sepay', [
                'id' => 889004,
                'transferType' => 'in',
                'transferAmount' => 50000,
                'content' => 'NHOSUB KHACHSOSANH ' . $req->verification_code,
                'referenceCode' => 'FT889004',
            ])->assertOk();

        // Fallback username: vẫn cộng theo cách cũ (50k → ví).
        // Ghi chú: tiền nạp 50k được cộng nhưng yêu cầu 100k vẫn pending.
        $this->assertSame(50000, $user->fresh()->balance);
        $this->assertSame('pending', $req->fresh()->status);
    }

    public function test_hai_khach_cung_luc_khong_nham_nhau_nho_ma(): void
    {
        $a = User::factory()->create(['username' => 'khacha', 'balance' => 0]);
        $b = User::factory()->create(['username' => 'khachb', 'balance' => 0]);

        $reqA = $this->deposits->createManualRequest($a->id, 50000);
        $reqB = $this->deposits->createManualRequest($b->id, 50000);

        $this->assertNotSame($reqA->verification_code, $reqB->verification_code);

        // Hai giao dịch cùng tiền nhưng mã khác nhau → cộng đúng người.
        $this->withHeaders(['Authorization' => 'Apikey ' . $this->apiKey])
            ->postJson('/webhook/sepay', [
                'id' => 889005, 'transferType' => 'in', 'transferAmount' => 50000,
                'content' => 'NHOSUB KHACHA ' . $reqA->verification_code, 'referenceCode' => 'FT889005',
            ])->assertOk();

        $this->withHeaders(['Authorization' => 'Apikey ' . $this->apiKey])
            ->postJson('/webhook/sepay', [
                'id' => 889006, 'transferType' => 'in', 'transferAmount' => 50000,
                'content' => 'NHOSUB KHACHB ' . $reqB->verification_code, 'referenceCode' => 'FT889006',
            ])->assertOk();

        $this->assertSame(50000, $a->fresh()->balance);
        $this->assertSame(50000, $b->fresh()->balance);
    }
}
