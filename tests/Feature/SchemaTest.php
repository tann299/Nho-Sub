<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_du_cac_bang_duoc_tao(): void
    {
        $expected = [
            'users', 'password_reset_tokens', 'sessions', 'cache', 'jobs',
            'categories', 'services', 'orders', 'order_refunds',
            'deposit_requests', 'wallet_transactions', 'settings',
            'api_sync_logs', 'webhook_logs', 'payos_transactions', 'notifications',
        ];

        foreach ($expected as $table) {
            $this->assertTrue(Schema::hasTable($table), "Thiếu bảng {$table}");
        }
    }

    public function test_kieu_tien_dung_bigint_va_decimal(): void
    {
        // Tiền VND phải là BIGINT (đồng nguyên), giá USD là DECIMAL — không dùng float.
        $this->assertSame('bigint', Schema::getColumnType('users', 'balance'));
        $this->assertSame('bigint', Schema::getColumnType('orders', 'total_amount'));
        $this->assertSame('bigint', Schema::getColumnType('wallet_transactions', 'amount'));
        $this->assertSame('decimal', Schema::getColumnType('services', 'cost_usd'));
    }

    public function test_username_va_email_duy_nhat(): void
    {
        User::factory()->create(['username' => 'khach01', 'email' => 'a@a.vn']);
        $this->expectException(\Illuminate\Database\QueryException::class);
        User::factory()->create(['username' => 'khach01', 'email' => 'b@b.vn']);
    }

    public function test_ma_don_client_token_duy_nhat(): void
    {
        Order::factory()->create(['client_token' => 'abc-123']);
        $this->expectException(\Illuminate\Database\QueryException::class);
        Order::factory()->create(['client_token' => 'abc-123']);
    }
}
