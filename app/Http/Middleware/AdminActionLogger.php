<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ghi log mọi thao tác ghi (POST) trong khu vực admin.
 *
 * Mục đích: truy vết khi có biến động số dư/don hàng bất thường —
 * ai làm, khi nào, vào đâu. Log nội bộ (storage/logs), không chứa
 * thông tin bí mật (key/mật khẩu đã được che ở các controller).
 */
class AdminActionLogger
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('post') && auth()->check() && auth()->user()->isAdmin()) {
            $user = auth()->user();

            Log::channel('admin')->info('admin.action', [
                'admin_id' => $user->id,
                'admin_username' => $user->username,
                'route' => $request->route()?->getName() ?? $request->path(),
                'ip' => $request->ip(),
                // Chỉ ghi field an toàn — loại trừ password/token/key
                'input_keys' => array_keys($request->except([
                    '_token', 'password', 'password_confirmation', 'current_password',
                    'topsubre_api_key', 'sepay_api_key', 'payos_client_id',
                    'payos_api_key', 'payos_checksum_key', 'key', 'api_key',
                ])),
            ]);
        }

        return $next($request);
    }
}
