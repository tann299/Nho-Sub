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
}
