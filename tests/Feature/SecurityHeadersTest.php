<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kiểm tra các tiêu chí bảo mật hệ thống (yêu cầu rà soát 13 mục).
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_headers_bao_mat_ton_tai_tren_moi_phan_hoi(): void
    {
        $res = $this->get('/');

        $res->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_co_content_security_policy(): void
    {
        $res = $this->get('/');
        $csp = $res->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp, 'Thiếu CSP header');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString('frame-ancestors', $csp);
        // Chỉ cho phép VietQR ngoài domain — mọi CDN lạ bị chặn.
        $this->assertStringContainsString('img.vietqr.io', $csp);
    }

    public function test_production_ep_https(): void
    {
        config(['app.env' => 'production']);

        $res = $this->get('/', ['HTTPS' => 'off']);

        $this->assertTrue(
            $res->isRedirect() || $res->headers->get('Location') !== null,
            'Production phải chuyển hướng http → https'
        );
        $this->assertStringStartsWith('https://', (string) $res->headers->get('Location'));
    }

    public function test_local_khong_ep_https(): void
    {
        config(['app.env' => 'local']);

        $res = $this->get('/');

        $res->assertOk(); // không redirect
    }

    public function test_cookie_session_co_flag_an_toan(): void
    {
        config([
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
        ]);

        $user = \App\Models\User::factory()->create([
            'password_hash' => \Illuminate\Support\Facades\Hash::make('MatKhau@123'),
        ]);

        $res = $this->post('/login', ['login' => $user->username, 'password' => 'MatKhau@123']);
        $cookies = $res->headers->getCookies();

        $sessionCookie = null;
        foreach ($cookies as $cookie) {
            if (str_contains($cookie->getName(), 'session')) {
                $sessionCookie = $cookie;
            }
        }

        $this->assertNotNull($sessionCookie, 'Phải có cookie session');
        $this->assertTrue($sessionCookie->isHttpOnly(), 'Cookie phải httpOnly');
        $this->assertTrue($sessionCookie->isSecure(), 'Cookie phải Secure');
        $this->assertSame('lax', strtolower((string) $sessionCookie->getSameSite()), 'SameSite phải là Lax');
    }

    public function test_dang_nhap_sai_5_lan_tra_429(): void
    {
        $user = \App\Models\User::factory()->create([
            'username' => 'secbrute',
            'password_hash' => \Illuminate\Support\Facades\Hash::make('MatKhau@123'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login' => 'secbrute', 'password' => 'sai']);
        }

        $this->post('/login', ['login' => 'secbrute', 'password' => 'MatKhau@123'])
            ->assertStatus(429, 'Sai 5 lần phải bị chặn 15 phút');
    }
}
