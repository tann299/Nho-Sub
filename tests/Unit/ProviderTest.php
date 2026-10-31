<?php

namespace Tests\Unit;

use App\Exceptions\ProviderException;
use App\Providers\TopSubRe\TopSubReProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProviderTest extends TestCase
{
    public function test_topsubre_gui_dung_action_va_key(): void
    {
        Http::fake([
            'likenhanh.pro/*' => Http::response(['balance' => '12.34', 'currency' => 'USD'], 200),
        ]);

        $provider = new TopSubReProvider('secret-key', 'https://likenhanh.pro/api/v2');
        $balance = $provider->balance();

        $this->assertSame('12.34', $balance->usd);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'https://likenhanh.pro/api/v2'
                && $body['key'] === 'secret-key'
                && $body['action'] === 'balance';
        });
    }

    public function test_topsubre_loi_tra_ve_nem_provider_exception(): void
    {
        Http::fake([
            'likenhanh.pro/*' => Http::response(['error' => 'Invalid API key'], 200),
        ]);

        $provider = new TopSubReProvider('bad-key', 'https://likenhanh.pro/api/v2');

        $this->expectException(ProviderException::class);
        $provider->balance();
    }

    public function test_topsubre_add_order_doc_order_id(): void
    {
        Http::fake([
            'likenhanh.pro/*' => Http::response(['order' => 987654], 200),
        ]);

        $provider = new TopSubReProvider('k', 'https://likenhanh.pro/api/v2');
        $result = $provider->addOrder('123', 'https://fb.com/x', 500);

        $this->assertSame('987654', $result->providerOrderId);
    }

    public function test_topsubre_status_map_dung(): void
    {
        Http::fake([
            'likenhanh.pro/*' => Http::response([
                'status' => 'Partial',
                'start_count' => 100,
                'remains' => '250',
            ], 200),
        ]);

        $provider = new TopSubReProvider('k', 'https://likenhanh.pro/api/v2');
        $status = $provider->orderStatus('987654');

        $this->assertSame('partial', $status->status);
        $this->assertSame(100, $status->startCount);
        $this->assertSame(250, $status->remains);
    }

    public function test_topsubre_khong_bao_gio_ghi_key_vao_loi(): void
    {
        Http::fake(['likenhanh.pro/*' => Http::response(['error' => 'x'], 200)]);

        $provider = new TopSubReProvider('TOPSECRET-KEY-9999', 'https://likenhanh.pro/api/v2');

        try {
            $provider->balance();
            $this->fail('Phải ném ProviderException');
        } catch (ProviderException $e) {
            $this->assertStringNotContainsString('TOPSECRET-KEY-9999', $e->getMessage());
        }
    }
}
