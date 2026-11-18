<?php

namespace Tests\Unit;

use App\Exceptions\NapxuException;
use App\Services\Napxu\NapxuClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NapxuClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_get_authenticates_in_header_only(): void
    {
        Http::fake(['napxu.vn/api/auth/balance*' => Http::response(['balance' => 250000, 'currency' => 'VND'])]);
        $result = (new NapxuClient('napx_test_secret'))->get('/auth/balance');
        $this->assertSame(250000, $result['balance']);
        Http::assertSent(fn ($r) => $r->method() === 'GET'
            && $r->header('Authorization') === ['Bearer napx_test_secret']
            && ! str_contains($r->url(), 'napx_test_secret'));
    }

    public function test_post_sends_documented_payload_without_key(): void
    {
        Http::fake(['napxu.vn/api/topup/orders' => Http::response(['order' => ['id' => 10, 'order_ref' => 'TU-10']])]);
        $result = (new NapxuClient('napx_test_secret'))->post('/topup/orders', [
            'tiktok_account_id' => 5, 'package_id' => 2, 'coins' => 1000, 'server' => 'server6',
        ]);
        $this->assertSame('TU-10', $result['order']['order_ref']);
        Http::assertSent(fn ($r) => $r->method() === 'POST'
            && $r->data() === ['tiktok_account_id' => 5, 'package_id' => 2, 'coins' => 1000, 'server' => 'server6']);
        Http::assertSentCount(1);
    }

    public function test_rejected_order_is_definitive_and_redacts_secrets(): void
    {
        Http::fake(['napxu.vn/*' => Http::response(['error' => 'Invalid napx_test_secret cookie sid_guard=test pin 123456'], 400)]);
        try {
            (new NapxuClient('napx_test_secret'))->post('/topup/orders', ['cookie' => 'sid_guard=test', 'pin' => '123456']);
            $this->fail('Expected rejection');
        } catch (NapxuException $e) {
            $this->assertFalse($e->ambiguous);
            foreach (['napx_test_secret', 'sid_guard=test', '123456'] as $secret) {
                $this->assertStringNotContainsString($secret, $e->getMessage());
            }
            $this->assertNull($e->getPrevious());
        }
    }

    public function test_invalid_key_never_uses_raw_response(): void
    {
        Http::fake(['napxu.vn/*' => Http::response(['error' => 'bad napx_test_secret'], 401)]);
        try {
            (new NapxuClient('napx_test_secret'))->get('/auth/balance');
            $this->fail('Expected rejection');
        } catch (NapxuException $e) {
            $this->assertFalse($e->ambiguous);
            $this->assertStringNotContainsString('napx_test_secret', $e->getMessage());
        }
    }

    public function test_timeout_is_ambiguous_and_not_retried(): void
    {
        Http::fake(['napxu.vn/*' => Http::failedConnection()]);
        try {
            (new NapxuClient('napx_test_secret'))->post('/topup/orders');
            $this->fail('Expected connection exception');
        } catch (NapxuException $e) {
            $this->assertTrue($e->ambiguous);
            $this->assertNull($e->getPrevious());
        }
    }

    public function test_server_error_is_ambiguous(): void
    {
        Http::fake(['napxu.vn/*' => Http::response(['error' => 'Service failed after charging'], 500)]);
        try {
            (new NapxuClient('napx_test_secret'))->post('/topup/orders');
            $this->fail('Expected server exception');
        } catch (NapxuException $e) {
            $this->assertTrue($e->ambiguous);
        }
        Http::assertSentCount(1);
    }

    public function test_invalid_json_is_not_reported_as_success(): void
    {
        Http::fake(['napxu.vn/*' => Http::response('<html>Not JSON</html>', 200)]);
        try {
            (new NapxuClient('napx_test_secret'))->post('/topup/orders');
            $this->fail('Expected invalid response');
        } catch (NapxuException $e) {
            $this->assertTrue($e->ambiguous);
        }
    }

    public function test_redirect_does_not_send_token_to_another_host(): void
    {
        Http::fake(['napxu.vn/*' => Http::response('', 302, ['Location' => 'https://other.example'])]);
        $this->expectException(NapxuException::class);
        (new NapxuClient('napx_test_secret'))->get('/auth/balance');
    }

    public function test_missing_key_fails_before_http(): void
    {
        Http::fake();
        try {
            (new NapxuClient(''))->get('/auth/balance');
            $this->fail('Expected missing key');
        } catch (NapxuException $e) {
            $this->assertFalse($e->ambiguous);
        }
        Http::assertNothingSent();
    }
}
