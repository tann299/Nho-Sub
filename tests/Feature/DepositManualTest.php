<?php

namespace Tests\Feature;

use App\Models\DepositRequest;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\DepositService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DepositManualTest extends TestCase
{
    use RefreshDatabase;

    private DepositService $deposits;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        app(SettingService::class)->set('min_deposit', '10000');
        $this->deposits = app(DepositService::class);
        $this->user = User::factory()->create(['balance' => 0]);
    }

    public function test_tao_yeu_cau_nap_tay_pending(): void
    {
        $req = $this->deposits->createManualRequest($this->user->id, 50000, 'bank', 'Nạp hộ');

        $this->assertSame('pending', $req->status);
        $this->assertSame(50000, $req->amount);
        $this->assertSame('NHOSUB ' . strtoupper($this->user->username), $req->transfer_code);
    }

    public function test_duoi_muc_toi_thieu_bi_tu_choi(): void
    {
        $this->expectException(ValidationException::class);
        $this->deposits->createManualRequest($this->user->id, 5000, 'bank', null);
    }

    public function test_khong_tao_duoc_hai_yeu_cau_tay_pending(): void
    {
        $this->deposits->createManualRequest($this->user->id, 50000, 'bank', null);

        $this->expectException(ValidationException::class);
        $this->deposits->createManualRequest($this->user->id, 60000, 'bank', null);
    }

    public function test_duyet_cong_tien_dung_mot_lan(): void
    {
        $req = $this->deposits->createManualRequest($this->user->id, 50000, 'bank', null);

        $this->deposits->approveManual($req, 1, 'Đã nhận');
        $this->deposits->approveManual($req->fresh(), 1, 'Đã nhận lần 2');

        $this->assertSame(50000, $this->user->fresh()->balance);
        $this->assertSame(1, WalletTransaction::count());
        $this->assertSame('approved', $req->fresh()->status);
    }

    public function test_tu_choi_bat_buoc_ly_do(): void
    {
        $req = $this->deposits->createManualRequest($this->user->id, 50000, 'bank', null);

        $this->expectException(ValidationException::class);
        $this->deposits->rejectManual($req, 1, '   ');
    }

    public function test_tu_choi_khong_cong_tien(): void
    {
        $req = $this->deposits->createManualRequest($this->user->id, 50000, 'bank', null);

        $this->deposits->rejectManual($req, 1, 'Chưa nhận được tiền');

        $this->assertSame(0, $this->user->fresh()->balance);
        $this->assertSame('rejected', $req->fresh()->status);
        $this->assertSame('Chưa nhận được tiền', $req->fresh()->admin_note);
    }

    public function test_yeu_cau_payos_qua_han_tu_dong_expired(): void
    {
        DepositRequest::create([
            'user_id' => $this->user->id,
            'amount' => 20000,
            'method' => 'payos',
            'transfer_code' => 'NHOSUB ' . strtoupper($this->user->username),
            'status' => 'pending',
            'expires_at' => now()->subMinute(),
            'created_at' => now()->subMinutes(31),
        ]);

        $count = $this->deposits->expireStaleRequests();

        $this->assertSame(1, $count);
        $this->assertSame('expired', DepositRequest::first()->status);
    }

    public function test_yeu_cau_chua_qua_han_khong_bi_dong(): void
    {
        DepositRequest::create([
            'user_id' => $this->user->id,
            'amount' => 20000,
            'method' => 'payos',
            'transfer_code' => 'x',
            'status' => 'pending',
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
        ]);

        $this->assertSame(0, $this->deposits->expireStaleRequests());
        $this->assertSame('pending', DepositRequest::first()->status);
    }

    public function test_sau_khi_het_han_tao_duoc_yeu_cau_moi(): void
    {
        $cu = $this->deposits->createManualRequest($this->user->id, 50000, 'bank', null);
        $cu->forceFill(['status' => 'expired'])->save();

        $moi = $this->deposits->createManualRequest($this->user->id, 60000, 'bank', null);

        $this->assertSame('pending', $moi->status);
    }
}
