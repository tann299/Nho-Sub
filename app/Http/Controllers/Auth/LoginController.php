<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\TooManyLoginAttemptsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const DECAY_SECONDS = 900; // 15 phút

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $login = (string) $request->input('login');
        $password = (string) $request->input('password');

        // Khoá theo IP + tên đăng nhập để chặn dò mật khẩu.
        $throttleKey = 'login:' . mb_strtolower($login) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw new TooManyLoginAttemptsException(
                "Bạn đã thử quá nhiều lần. Vui lòng thử lại sau {$seconds} giây.",
                $seconds
            );
        }

        // Tìm theo username hoặc email, không phân biệt hoa thường.
        $user = User::whereRaw('LOWER(username) = ?', [mb_strtolower($login)])
            ->orWhereRaw('LOWER(email) = ?', [mb_strtolower($login)])
            ->first();

        // Luôn báo cùng một thông điệp để không lộ tài khoản có tồn tại hay không.
        $invalid = ValidationException::withMessages([
            'login' => 'Tên đăng nhập hoặc mật khẩu không đúng.',
        ]);

        if (! $user || ! Hash::check($password, $user->password_hash)) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            if ($user) {
                $user->increment('failed_login_attempts');
            }

            throw $invalid;
        }

        if ($user->isLocked()) {
            throw ValidationException::withMessages([
                'login' => 'Tài khoản đã bị khoá. Vui lòng liên hệ quản trị viên để được hỗ trợ.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'failed_login_attempts' => 0,
        ])->save();

        return redirect()->intended(
            $user->isAdmin() ? route('admin.dashboard') : route('panel.dashboard')
        );
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landing')->with('info', 'Bạn đã đăng xuất.');
    }
}
