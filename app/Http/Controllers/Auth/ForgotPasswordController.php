<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function create(): View
    {
        // Cảnh báo khi hệ thống chưa cấu hình gửi mail thật (MAIL_MAILER=log).
        $mailConfigured = config('mail.default') !== 'log';

        return view('auth.forgot', compact('mailConfigured'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
        ]);

        // Luôn trả về thông điệp chung để không lộ email có tồn tại hay không.
        Password::sendResetLink($request->only('email'));

        return back()->with('success', 'Nếu email tồn tại trong hệ thống, bạn sẽ nhận được liên kết đặt lại mật khẩu.');
    }
}
