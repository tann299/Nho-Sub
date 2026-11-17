<?php

namespace Tests\Unit;

use App\Exceptions\ProviderException;
use App\Providers\Napxu\NapxuProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NapxuProviderTest extends TestCase
{
    private const BASE = 'https://napxu.vn/api';

    private function makeProvider(int $rate = 26000): NapxuProvider
    {
        return new NapxuProvider('napx_SECRET-KEY-9999', self::BASE, $rate);
    }

    public function test_balance_gui_bearer_token_va_do_so_du_vnd(): void
    {
        Http::fake([
            self::BASE.'/auth/balance' => Http::response([
                'id' => 1014866, 'username' => 'ngoctan', 'balance' => 250000, 'currency' => 'VND',
            ], 200),
        ]);

        $balance = $this->makeProvider()->balance();

        // 250000 VND / 26000 = 9.615384 USD
        $this->assertSame('9.615385', $balance->usd);

        Http::assertSent(function ($request) {
            return $request->url() === self::BASE.'/auth/balance'
                && $request->header('Authorization')[0] === 'Bearer napx_SECRET-KEY-9999';
        });
    }

    public function test_balance_loi_401_nem_provider_exception_khong_lo_key(): void
    {
        Http::fake([
            self::BASE.'/auth/balance' => Http::response(['error' => 'API key sai định dạng.'], 401),
        ]);

        $provider = $this->makeProvider();

        try {
            $provider->balance();
            $this->fail('Phải ném ProviderException');
        } catch (ProviderException $e) {
            $this->assertStringNotContainsString('napx_SECRET-KEY-9999', $e->getMessage());
            $this->assertStringContainsString('API key', $e->getMessage());
        }
    }

    public function test_services_flatten_server_thanh_dich_vu(): void
    {
        Http::fake([
            self::BASE.'/boost/service-types' => Http::response([
                'platforms' => [
                    [
                        'id' => 2,
                        'nameVi' => 'Facebook',
                        'serviceTypes' => [
                            [
                                'id' => 13,
                                'nameVi' => 'Tăng Like Bài Viết',
                                'servers' => [
                                    [
                                        'id' => 1301,
                                        'label' => 'Tăng Like Bài Viết [Việt Nam]',
                                        'descriptionVi' => 'Like bài viết, tăng ngay',
                                        'pricePerUnit' => 10,
                                        'minQuantity' => 100,
                                        'maxQuantity' => 10000,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $services = $this->makeProvider()->services();

        $this->assertCount(1, $services);
        $svc = $services[0];

        $this->assertSame('napxu-1301', $svc->id);
        $this->assertSame('Facebook — Tăng Like Bài Viết — Tăng Like Bài Viết [Việt Nam]', $svc->name);
        $this->assertSame('Facebook — Tăng Like Bài Viết', $svc->category);
        $this->assertSame('Like bài viết, tăng ngay', $svc->description);
        // pricePerUnit=10 VND → 10000 VND/1000 lượt → 10000/26000 = 0.384615 USD
        // (làm tròn LÊN 6 số để cost không bị mất phần lẻ → không bán lỗ)
        $this->assertSame('0.384616', $svc->costUsd);
        $this->assertSame(100, $svc->minQty);
        $this->assertSame(10000, $svc->maxQty);
    }

    public function test_add_order_gui_serverid_link_quantity_va_doc_order_ref(): void
    {
        Http::fake([
            self::BASE.'/boost/orders/create' => Http::response([
                'message' => 'Đặt đơn thành công.',
                'order' => ['id' => 25, 'orderRef' => 'BO-928471', 'status' => 'pending', 'totalPrice' => 5000],
                'newBalance' => 245000,
            ], 200),
        ]);

        $result = $this->makeProvider()->addOrder('napxu-1301', 'https://vt.tiktok.com/xyz', 500);

        $this->assertSame('BO-928471', $result->providerOrderId);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === self::BASE.'/boost/orders/create'
                && $body['serverId'] === 1301
                && $body['link'] === 'https://vt.tiktok.com/xyz'
                && $body['quantity'] === 500;
        });
    }

    public function test_add_order_loi_nghiepVu_nem_provider_exception(): void
    {
        Http::fake([
            self::BASE.'/boost/orders/create' => Http::response(
                ['error' => 'Số lượng phải từ 100 đến 1,000,000.'],
                400
            ),
        ]);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Số lượng phải từ 100 đến 1,000,000.');

        $this->makeProvider()->addOrder('napxu-4', 'https://example.com/x', 50);
    }

    public function test_status_tim_don_trong_danh_sach_va_map_dung(): void
    {
        Http::fake([
            self::BASE.'/boost/orders*' => Http::response([
                'orders' => [
                    ['id' => 99, 'orderRef' => 'BO-OTHER', 'status' => 'success', 'startCount' => null, 'remains' => null],
                    [
                        'id' => 25,
                        'orderRef' => 'BO-928471',
                        'status' => 'in_progress',
                        'startCount' => '100',
                        'remains' => '250',
                    ],
                ],
                'pagination' => ['page' => 1, 'total' => 2],
            ], 200),
        ]);

        $status = $this->makeProvider()->orderStatus('BO-928471');

        $this->assertSame('processing', $status->status);
        $this->assertSame(100, $status->startCount);
        $this->assertSame(250, $status->remains);
    }

    public function test_status_khong_thay_don_tra_unknown(): void
    {
        Http::fake([
            self::BASE.'/boost/orders*' => Http::response([
                'orders' => [],
                'pagination' => ['page' => 1, 'total' => 0],
            ], 200),
        ]);

        $status = $this->makeProvider()->orderStatus('BO-KHONG-TON-TAI');

        $this->assertSame('unknown', $status->status);
        $this->assertNull($status->startCount);
        $this->assertNull($status->remains);
    }

    public function test_status_map_success_refund_failed_va_partial(): void
    {
        $cases = [
            'pending' => 'pending',
            'processing' => 'processing',
            'in_progress' => 'processing',
            'success' => 'completed',
            'completed' => 'completed',
            'partial' => 'partial',
            'refund' => 'canceled',
            'canceled' => 'canceled',
            'failed' => 'canceled',
        ];

        // Http::fake gộp stub và stub đầu tiên thắng — dùng sequence để mỗi lần
        // gọi orderStatus() nhận đúng response tương ứng từng case.
        $sequence = Http::sequence();
        foreach (array_keys($cases) as $raw) {
            $sequence->push(['orders' => [['id' => 1, 'orderRef' => 'BO-X', 'status' => $raw]]], 200);
        }

        Http::fake([self::BASE.'/boost/orders*' => $sequence]);

        foreach ($cases as $raw => $expected) {
            $status = $this->makeProvider()->orderStatus('BO-X');
            $this->assertSame($expected, $status->status, "status {$raw} phải map thành {$expected}");
        }
    }
}
