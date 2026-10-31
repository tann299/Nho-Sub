<?php

namespace Tests\Feature;

use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SettingServiceTest extends TestCase
{
    use RefreshDatabase;

    private SettingService $settings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->settings = app(SettingService::class);
    }

    public function test_doc_gia_tri_co_kieu_dung(): void
    {
        $this->settings->set('exchange_rate', '26500');
        $this->settings->set('demo_mode', '1');

        $this->assertSame('26500', $this->settings->get('exchange_rate'));
        $this->assertSame(26500, $this->settings->getInt('exchange_rate'));
        $this->assertTrue($this->settings->getBool('demo_mode'));
        $this->assertSame('9.99', $this->settings->getDecimal('khong_ton_tai', '9.99'));
    }

    public function test_secret_duoc_ma_hoa_trong_database(): void
    {
        $this->settings->setSecret('topsubre_api_key', 'super-secret-key-1234');

        $raw = DB::table('settings')->where('key', 'topsubre_api_key')->value('value');

        $this->assertNotSame('super-secret-key-1234', $raw, 'Key phải được mã hoá trong DB');
        $this->assertSame('super-secret-key-1234', $this->settings->getSecret('topsubre_api_key'));
    }

    public function test_masked_secret_chi_hien_4_ky_tu_cuoi(): void
    {
        $this->settings->setSecret('sepay_api_key', 'abcdefgh1234');

        $this->assertSame('••••••••1234', $this->settings->maskedSecret('sepay_api_key'));
        $this->assertTrue($this->settings->hasSecret('sepay_api_key'));
        $this->assertNull($this->settings->maskedSecret('khong_co'));
    }

    public function test_cache_bi_xoa_khi_set(): void
    {
        $this->settings->set('exchange_rate', '26000');
        $this->assertSame(26000, $this->settings->getInt('exchange_rate'));

        $this->settings->set('exchange_rate', '27000');
        $this->assertSame(27000, $this->settings->getInt('exchange_rate'));
    }
}
