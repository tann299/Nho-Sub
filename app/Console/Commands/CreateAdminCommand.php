<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminCommand extends Command
{
    protected $signature = 'nhosub:create-admin
                            {--username= : Tên đăng nhập}
                            {--email= : Email}
                            {--password= : Mật khẩu}';

    protected $description = 'Tạo tài khoản quản trị viên (không cần seeder)';

    public function handle(): int
    {
        $username = $this->option('username') ?: $this->ask('Tên đăng nhập');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->option('password') ?: $this->secret('Mật khẩu (tối thiểu 8 ký tự)');

        $validator = Validator::make(compact('username', 'email', 'password'), [
            'username' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[A-Za-z0-9_]+$/', 'unique:users,username'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::create([
            'username' => $username,
            'email' => mb_strtolower($email),
            'password_hash' => Hash::make($password),
            'full_name' => 'Quản trị viên',
            'role' => User::ROLE_ADMIN,
            'balance' => 0,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->info("Đã tạo admin '{$username}'.");

        return self::SUCCESS;
    }
}
