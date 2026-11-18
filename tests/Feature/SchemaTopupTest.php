<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaTopupTest extends TestCase
{
    use RefreshDatabase;

    public function test_bang_topup_co_du_cot(): void
    {
        $this->assertTrue(Schema::hasTable('tiktok_accounts'));
        $this->assertTrue(Schema::hasTable('topup_packages'));
        $this->assertTrue(Schema::hasTable('topup_orders'));

        foreach (['user_id', 'napxu_account_id', 'username', 'coins'] as $col) {
            $this->assertTrue(Schema::hasColumn('tiktok_accounts', $col), "tiktok_accounts.$col");
        }
        foreach (['name', 'coins', 'cost_price', 'sell_price', 'server', 'napxu_package_id', 'is_active'] as $col) {
            $this->assertTrue(Schema::hasColumn('topup_packages', $col), "topup_packages.$col");
        }
        foreach (['user_id', 'tiktok_account_id', 'topup_package_id', 'coins', 'sell_price', 'cost_price',
                  'napxu_order_ref', 'status', 'synced_at', 'synced_count'] as $col) {
            $this->assertTrue(Schema::hasColumn('topup_orders', $col), "topup_orders.$col");
        }
    }

    public function test_topup_safety_fields_exist(): void
    {
        foreach (['client_token', 'napxu_order_id', 'server', 'napxu_package_id', 'refunded_amount', 'error_message'] as $column) {
            $this->assertTrue(Schema::hasColumn('topup_orders', $column), $column);
        }
        $this->assertTrue(Schema::hasColumn('tiktok_accounts', 'deleted_at'));
    }

    public function test_models_tao_va_doc_duoc(): void
    {
        $user = \App\Models\User::factory()->create();
        $acc = \App\Models\TikTokAccount::create([
            'user_id' => $user->id, 'napxu_account_id' => 777, 'username' => '@a',
        ]);
        $pkg = \App\Models\TopupPackage::create([
            'name' => 'Gói 100', 'coins' => 100, 'cost_price' => 27800,
            'sell_price' => 30000, 'server' => 'server1', 'napxu_package_id' => 5,
        ]);
        $order = \App\Models\TopupOrder::create([
            'user_id' => $user->id, 'tiktok_account_id' => $acc->id,
            'topup_package_id' => $pkg->id, 'coins' => 100,
            'sell_price' => 30000, 'cost_price' => 27800,
        ]);

        // Status có default DB — đọc lại từ DB để khẳng định default được áp dụng.
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertFalse($order->isFinal());
        $this->assertSame($user->id, $acc->user->id);
        $this->assertSame($pkg->id, $order->package->id);
    }
}
