<?php

use App\Exceptions\TooManyLoginAttemptsException;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SecurityHeaders::class]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);

        // Webhook cổng thanh toán xác thực bằng chữ ký riêng, không dùng CSRF.
        $middleware->validateCsrfTokens(except: [
            'webhook/sepay',
            'webhook/payos',
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('panel.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Chặn dò mật khẩu: trả 429 kèm lỗi hiển thị ở ô login.
        $exceptions->render(function (TooManyLoginAttemptsException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => ['login' => [$e->getMessage()]],
                ], 429);
            }

            return redirect()
                ->route('login')
                ->withErrors(['login' => $e->getMessage()])
                ->withInput($request->only('login'))
                ->setStatusCode(429);
        });
    })->create();
