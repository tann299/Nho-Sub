<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // CSP: chỉ cho phép tài nguyên từ chính domain + VietQR (ảnh QR chuyển khoản).
        // 'unsafe-inline' cần cho Blade inline script (tạm tính ở trang đặt đơn).
        $response->headers->set('Content-Security-Policy',
            "default-src 'self'; img-src 'self' data: https://img.vietqr.io; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'"
        );

        // Bật HTTPS ở production: chuyển hướng mọi request http → https.
        // APP_ENV=local (máy dev) không ép để php artisan serve vẫn chạy được.
        if (config('app.env') === 'production' && ! $request->secure()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $response;
    }
}
