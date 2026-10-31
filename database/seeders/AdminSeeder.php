<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Tạo tài khoản admin đầu tiên.
 * Chỉ chạy khi ADMIN_SEED_ENABLED=true VÀ chưa có dấu .admin_seeded
 * → sau lần đầu tự khoá, tránh tạo lại/ghi đè mật khẩu trên production.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        if (! filter_var(env('ADMIN_SEED_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        if (Storage::disk('local')->exists('.admin_seeded')) {
            return;
        }

        if (User::where('role', User::ROLE_ADMIN)->exists()) {
            Storage::disk('local')->put('.admin_seeded', now()->toDateTimeString());

            return;
        }

        User::create([
            'username' => env('ADMIN_USERNAME', 'admin'),
            'email' => env('ADMIN_EMAIL', 'admin@nhosub.vn'),
            'password_hash' => Hash::make(env('ADMIN_PASSWORD', 'Admin@123456')),
            'full_name' => 'Quản trị viên',
            'role' => User::ROLE_ADMIN,
            'balance' => 0,
            'status' => User::STATUS_ACTIVE,
        ]);

        Storage::disk('local')->put('.admin_seeded', now()->toDateTimeString());
    }
}
