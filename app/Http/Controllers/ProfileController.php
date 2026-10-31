<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('panel.profile', ['user' => $request->user()]);
    }

    /** Chỉ cho sửa họ tên, SĐT, email. Username và role bị bỏ qua hoàn toàn. */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'full_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\s.-]{8,20}$/'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
        ], [
            'email.unique' => 'Email đã được dùng bởi tài khoản khác.',
            'phone.regex' => 'Số điện thoại không hợp lệ.',
        ]);

        $user->forceFill([
            'full_name' => $data['full_name'] ?? $user->full_name,
            'phone' => $data['phone'] ?? $user->phone,
            'email' => mb_strtolower($data['email']),
        ])->save();

        return back()->with('success', 'Đã cập nhật hồ sơ.');
    }
}
