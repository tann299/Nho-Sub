<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\RefundLimitExceededException;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wallet = app(WalletService::class);
    }

    public function test_cong_tien_va_ghi_giao_dich(): void
    {
        $user = User::factory()->create(['balance' => 0]);

        $tx = $this->wallet->apply($user->id, WalletTransaction::TYPE_DEPOSIT, 100000, 'deposit:test:1', 'Nạp tiền');

        $this->assertSame(100000, $user->fresh()->balance);
        $this->assertSame(100000, $tx->amount);
        $this->assertSame(100000, $tx->balance_after);
        $this->assertSame('deposit:test:1', $tx->idempotency_key);
    }

    public function test_tru_tien_khi_du_so_du(): void
    {
        $user = User::factory()->create(['balance' => 50000]);

        $this->wallet->apply($user->id, WalletTransaction::TYPE_ORDER_CHARGE, -30000, 'order:1:charge');

        $this->assertSame(20000, $user->fresh()->balance);
    }

    public function test_tru_qua_so_du_nem_exception_va_khong_doi_so_du(): void
    {
        $user = User::factory()->create(['balance' => 10000]);

        $this->expectException(InsufficientBalanceException::class);

        try {
            $this->wallet->apply($user->id, WalletTransaction::TYPE_ORDER_CHARGE, -20000, 'order:2:charge');
        } finally {
            $this->assertSame(10000, $user->fresh()->balance);
            $this->assertSame(0, WalletTransaction::count());
        }
    }

    public function test_cung_mot_key_chi_ap_dung_mot_lan(): void
    {
        $user = User::factory()->create(['balance' => 0]);

        $first = $this->wallet->apply($user->id, WalletTransaction::TYPE_DEPOSIT, 50000, 'deposit:sepay:TX-001');
        $second = $this->wallet->apply($user->id, WalletTransaction::TYPE_DEPOSIT, 50000, 'deposit:sepay:TX-001');

        $this->assertSame($first->id, $second->id, 'Lần hai phải trả về giao dịch cũ');
        $this->assertSame(50000, $user->fresh()->balance, 'Số dư không được cộng hai lần');
        $this->assertSame(1, WalletTransaction::count());
    }

    public function test_refund_vuot_cap_bi_chan(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        $order = \App\Models\Order::factory()->create(['user_id' => $user->id, 'total_amount' => 50000]);

        $this->expectException(RefundLimitExceededException::class);

        $this->wallet->apply(
            $user->id,
            WalletTransaction::TYPE_REFUND,
            60000,
            "order:{$order->id}:refund:1",
            null,
            $order->id,
            null,
            50000
        );
    }

    public function test_refund_trong_cap_duoc_phep(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        $order = \App\Models\Order::factory()->create(['user_id' => $user->id, 'total_amount' => 50000]);

        $this->wallet->apply(
            $user->id, WalletTransaction::TYPE_REFUND, 50000,
            "order:{$order->id}:refund:1", null, $order->id, null, 50000
        );

        $this->assertSame(50000, $user->fresh()->balance);
    }

    public function test_so_du_luon_bang_tong_giao_dich(): void
    {
        $user = User::factory()->create(['balance' => 0]);

        $this->wallet->apply($user->id, WalletTransaction::TYPE_DEPOSIT, 200000, 'deposit:a');
        $this->wallet->apply($user->id, WalletTransaction::TYPE_ORDER_CHARGE, -75000, 'order:1:charge');
        $this->wallet->apply($user->id, WalletTransaction::TYPE_REFUND, 25000, 'order:1:refund:1');
        $this->wallet->apply($user->id, WalletTransaction::TYPE_ADJUST, -5000, 'adjust:abc');

        $tong = (int) WalletTransaction::where('user_id', $user->id)->sum('amount');

        $this->assertSame($tong, $user->fresh()->balance);
        $this->assertSame(145000, $tong);
    }

    public function test_goi_lien_tiep_cung_key_chi_cong_mot_lan(): void
    {
        $user = User::factory()->create(['balance' => 0]);

        $ketQua = [];

        for ($i = 0; $i < 3; $i++) {
            $ketQua[] = $this->wallet->apply($user->id, WalletTransaction::TYPE_DEPOSIT, 10000, 'deposit:race:1');
        }

        $this->assertSame(1, count(array_unique(array_map(fn ($t) => $t->id, $ketQua))));
        $this->assertSame(10000, $user->fresh()->balance);
        $this->assertSame(1, WalletTransaction::count());
    }

    public function test_khong_the_insert_truc_tiep_trung_key(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        $this->wallet->apply($user->id, WalletTransaction::TYPE_DEPOSIT, 1000, 'dup:1');

        $this->expectException(QueryException::class);

        DB::table('wallet_transactions')->insert([
            'user_id' => $user->id,
            'type' => 'deposit',
            'amount' => 1000,
            'balance_after' => 1000,
            'idempotency_key' => 'dup:1',
            'created_at' => now(),
        ]);
    }
}
