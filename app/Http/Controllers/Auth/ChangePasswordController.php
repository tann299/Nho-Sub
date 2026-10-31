<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ChangePasswordController extends Controller
{
    public function edit(): View
    {
        return view('auth.change-password');
    }

    public function update(ChangePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! Hash::check($request->string('current_password')->toString(), $user->password_hash)) {
            return back()->withErrors(['current_password' => 'Mật khẩu hiện tại không đúng.']);
        }

        $user->forceFill([
            'password_hash' => Hash::make($request->string('password')->toString()),
        ])->save();

        // Đổi mật khẩu thì huỷ các phiên đăng nhập khác để bảo mật.
        // (Auth::logoutOtherDevices() mặc định ghi đè cột `password`, không
        // phù hợp vì dự án dùng `password_hash` — xoá session của user thay thế.)
        \Illuminate\Support\Facades\DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        $request->session()->regenerate();

        return back()->with('success', 'Đổi mật khẩu thành công.');
    }
}
