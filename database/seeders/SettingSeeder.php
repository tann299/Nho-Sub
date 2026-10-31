<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/** Cấu hình mặc định. Key nhạy cảm để trống, admin nhập ở trang Cài đặt. */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'site_name' => 'NhoSub',
            'exchange_rate' => '26000',
            'default_markup_percent' => '30',
            'min_deposit' => '10000',
            'bank_name' => 'MB Bank',
            'bank_account' => '',
            'bank_owner' => '',
            'bank_bin' => '970422',
            'provider_balance_alert' => '100',
            'demo_mode' => 'auto',
            'demo_scenario' => 'auto',
            'sepay_enabled' => '1',
            'payos_enabled' => '0',
            'momo_enabled' => '0',
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value, 'updated_at' => now()]);
        }
    }
}
