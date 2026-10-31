<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_dang_ky_tao_tai_khoan_customer_voi_so_du_0(): void
    {
        $res = $this->post('/register', [
            'username' => 'khachmoi',
            'email' => 'khachmoi@example.com',
            'password' => 'MatKhau@123',
            'password_confirmation' => 'MatKhau@123',
            'full_name' => 'Nguyễn Văn A',
            'phone' => '0901234567',
        ]);

        $res->assertRedirect('/dashboard');

        $user = User::where('username', 'khachmoi')->first();
        $this->assertNotNull($user);
        $this->assertSame('customer', $user->role);
        $this->assertSame(0, $user->balance);
        $this->assertSame('active', $user->status);
        $this->assertTrue(Hash::check('MatKhau@123', $user->password_hash));
        $this->assertAuthenticatedAs($user);
    }

    public function test_dang_ky_tu_choi_username_trung_khong_phan_biet_hoa_thuong(): void
    {
        User::factory()->create(['username' => 'KhachVIP', 'email' => 'x@x.vn']);

        $this->post('/register', [
            'username' => 'khachvip',
            'email' => 'moi@example.com',
            'password' => 'MatKhau@123',
            'password_confirmation' => 'MatKhau@123',
        ])->assertSessionHasErrors('username');
    }

    public function test_mat_khau_duoi_8_ky_tu_bi_tu_choi(): void
    {
        $this->post('/register', [
            'username' => 'khach02',
            'email' => 'k2@example.com',
            'password' => 'abc123',
            'password_confirmation' => 'abc123',
        ])->assertSessionHasErrors('password');
    }

    public function test_dang_nhap_bang_username_hoac_email(): void
    {
        $user = User::factory()->create([
            'username' => 'khach01',
            'email' => 'khach01@example.com',
            'password_hash' => Hash::make('MatKhau@123'),
        ]);

        $this->post('/login', ['login' => 'khach01', 'password' => 'MatKhau@123'])
             ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout');

        $this->post('/login', ['login' => 'khach01@example.com', 'password' => 'MatKhau@123'])
             ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_dang_nhap_sai_bao_loi_chung(): void
    {
        User::factory()->create([
            'username' => 'khach01',
            'password_hash' => Hash::make('MatKhau@123'),
        ]);

        $this->post('/login', ['login' => 'khach01', 'password' => 'sai'])
             ->assertSessionHasErrors(['login' => 'Tên đăng nhập hoặc mật khẩu không đúng.']);
        $this->assertGuest();
    }

    public function test_tai_khoan_bi_khoa_khong_dang_nhap_duoc(): void
    {
        User::factory()->locked()->create([
            'username' => 'bikhoa',
            'password_hash' => Hash::make('MatKhau@123'),
        ]);

        $this->post('/login', ['login' => 'bikhoa', 'password' => 'MatKhau@123'])
             ->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_sai_mat_khau_5_lan_bi_chan(): void
    {
        User::factory()->create([
            'username' => 'bruteforce',
            'password_hash' => Hash::make('MatKhau@123'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login' => 'bruteforce', 'password' => 'sai']);
        }

        $this->post('/login', ['login' => 'bruteforce', 'password' => 'MatKhau@123'])
             ->assertStatus(429);
    }

    public function test_session_doi_id_sau_dang_nhap(): void
    {
        User::factory()->create([
            'username' => 'khach01',
            'password_hash' => Hash::make('MatKhau@123'),
        ]);

        $this->get('/login');
        $before = session()->getId();

        $this->post('/login', ['login' => 'khach01', 'password' => 'MatKhau@123']);

        $this->assertNotSame($before, session()->getId());
    }

    public function test_doi_mat_khau_dung_mat_khau_cu(): void
    {
        $user = User::factory()->create(['password_hash' => Hash::make('Cu@123456')]);

        $this->actingAs($user)->post('/settings/password', [
            'current_password' => 'Cu@123456',
            'password' => 'Moi@123456',
            'password_confirmation' => 'Moi@123456',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('Moi@123456', $user->fresh()->password_hash));
    }

    public function test_doi_mat_khau_sai_mat_khau_cu_bi_tu_choi(): void
    {
        $user = User::factory()->create(['password_hash' => Hash::make('Cu@123456')]);

        $this->actingAs($user)->post('/settings/password', [
            'current_password' => 'sai',
            'password' => 'Moi@123456',
            'password_confirmation' => 'Moi@123456',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('Cu@123456', $user->fresh()->password_hash));
    }

    public function test_khach_thuong_khong_vao_duoc_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_admin_vao_duoc_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin')->assertOk();
    }
}
