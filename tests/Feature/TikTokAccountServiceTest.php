<?php

namespace Tests\Feature;

use App\Exceptions\NapxuException;
use App\Models\TikTokAccount;
use App\Models\User;
use App\Services\Napxu\NapxuClient;
use App\Services\Napxu\TikTokAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TikTokAccountServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): TikTokAccountService
    {
        return new TikTokAccountService(new NapxuClient('napx_test_secret'));
    }

    private function account(): array
    {
        return ['id' => 77, 'username' => '@tester', 'nickname' => 'Tester', 'avatar' => 'https://p16.tiktokcdn.com/a.png',
            'coins' => 100, 'status' => 'live', 'level' => 1, 'country' => 'VN'];
    }

    public function test_qr_belongs_to_its_user_and_done_is_idempotent(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'napxu.vn/api/auth/tiktok/qr/generate' => Http::response([
                'sessionId' => str_repeat('a', 64), 'qrImage' => 'data:image/png;base64,iVBORw0KGgo=', 'timeRemaining' => 180,
            ]),
            'napxu.vn/api/auth/tiktok/qr/status/*' => Http::response(['status' => 'done', 'account' => $this->account()]),
        ]);
        $user = User::factory()->create();
        $session = $this->service()->createQrSession($user->id);
        $result = $this->service()->pollQrSession($user->id, $session['sessionId']);
        $this->assertSame('done', $result['status']);
        $this->assertSame('@tester', $result['account']['username']);
        $this->service()->pollQrSession($user->id, $session['sessionId']);
        $this->assertSame(1, TikTokAccount::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('tiktok_accounts', ['user_id' => $user->id, 'napxu_account_id' => 77, 'coins' => 100]);
    }

    public function test_another_user_cannot_poll_qr_even_with_session_id(): void
    {
        Http::fake(['napxu.vn/api/auth/tiktok/qr/generate' => Http::response([
            'sessionId' => str_repeat('b', 64), 'qrImage' => 'data:image/png;base64,iVBORw0KGgo=', 'timeRemaining' => 180,
        ])]);
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $session = $this->service()->createQrSession($owner->id);
        $this->expectException(ValidationException::class);
        $this->service()->pollQrSession($other->id, $session['sessionId']);
    }

    public function test_cookie_is_not_stored_in_database_or_response(): void
    {
        Http::preventStrayRequests();
        Http::fake(['napxu.vn/api/auth/tiktok/add' => Http::response(['account' => $this->account()])]);
        $user = User::factory()->create();
        $cookie = 'sid_guard=very-secret-cookie';
        DB::enableQueryLog();
        $account = $this->service()->addByCookie($user->id, $cookie);
        $this->assertSame(77, $account->napxu_account_id);
        $this->assertStringNotContainsString($cookie, json_encode(DB::getQueryLog()));
        $this->assertStringNotContainsString($cookie, $account->toJson());
        DB::disableQueryLog();
        Http::assertSent(fn ($r) => $r['cookie'] === $cookie);
    }

    public function test_remote_account_cannot_be_claimed_by_another_customer(): void
    {
        Http::fake(['napxu.vn/api/auth/tiktok/add' => Http::response(['account' => $this->account()])]);
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->service()->addByCookie($owner->id, 'sid_guard=owner');
        $this->expectException(ValidationException::class);
        $this->service()->addByCookie($other->id, 'sid_guard=other');
    }

    public function test_refresh_matches_id_not_first_account(): void
    {
        $user = User::factory()->create();
        $account = TikTokAccount::create(['user_id' => $user->id, 'napxu_account_id' => 77, 'username' => '@tester', 'coins' => 10]);
        Http::fake(['napxu.vn/api/auth/tiktok/accounts*' => Http::response([
            'accounts' => [array_merge($this->account(), ['id' => 999, 'coins' => 999]), array_merge($this->account(), ['coins' => 300])],
            'pagination' => ['page' => 1, 'totalPages' => 1],
        ])]);
        $this->service()->syncCoins($account);
        $this->assertSame(300, $account->fresh()->coins);
    }

    public function test_invalid_provider_account_does_not_create_link(): void
    {
        $user = User::factory()->create();
        Http::fake(['napxu.vn/*' => Http::response(['account' => ['id' => 0, 'username' => '']])]);
        try {
            $this->service()->addByCookie($user->id, 'sid_guard=secret');
            $this->fail('Expected invalid account');
        } catch (NapxuException) {
            $this->assertSame(0, TikTokAccount::count());
        }
    }
}
