<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        // Toàn bộ việc tạo tài khoản trong 1 transaction để không tạo user dở dang.
        $user = DB::transaction(function () use ($request) {
            return User::create([
                'username' => $request->string('username')->toString(),
                'email' => $request->string('email')->lower()->toString(),
                'password_hash' => Hash::make($request->string('password')->toString()),
                'full_name' => $request->input('full_name'),
                'phone' => $request->input('phone'),
                'role' => User::ROLE_CUSTOMER,
                'balance' => 0,
                'status' => User::STATUS_ACTIVE,
            ]);
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('panel.dashboard')
            ->with('success', 'Chào mừng bạn đến với NhoSub! Tài khoản đã được tạo.');
    }
}
