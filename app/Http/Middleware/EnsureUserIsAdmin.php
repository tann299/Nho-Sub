<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /** Kiểm tra quyền ở phía server — không bao giờ chỉ ẩn nút trên giao diện. */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user && $user->isAdmin(), 403, 'Bạn không có quyền truy cập trang này.');

        return $next($request);
    }
}
