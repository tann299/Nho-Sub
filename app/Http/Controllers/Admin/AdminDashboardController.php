<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DepositRequest;
use App\Models\Order;
use App\Models\User;
use App\Providers\ProviderManager;
use App\Services\SettingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(ProviderManager $providers, SettingService $settings): View
    {
        $today = now()->startOfDay();

        $revenue = (int) Order::where('created_at', '>=', $today)
            ->whereNotIn('status', [Order::STATUS_ERROR])
            ->sum('total_amount');

        $cost = (int) Order::where('created_at', '>=', $today)
            ->whereNotIn('status', [Order::STATUS_ERROR])
            ->sum('cost_amount');

        $refunded = (int) Order::where('created_at', '>=', $today)->sum('refunded_amount');

        $byStatus = Order::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        // Số dư nhà cung cấp — cache 5 phút, lỗi không làm sập trang.
        $providerBalance = Cache::remember('admin.provider_balance', 300, function () use ($providers) {
            try {
                return $providers->driver()->balance()->usd;
            } catch (\Throwable $e) {
                return null;
            }
        });

        $alertThreshold = (float) $settings->get('provider_balance_alert', '100');
        $lowBalance = $providerBalance !== null && (float) $providerBalance < $alertThreshold;

        return view('admin.dashboard', [
            'revenue' => $revenue,
            'cost' => $cost,
            'refunded' => $refunded,
            'profit' => $revenue - $refunded - $cost,
            'totalOrders' => array_sum($byStatus),
            'byStatus' => $byStatus,
            'userCount' => User::where('role', 'customer')->count(),
            'pendingDeposits' => DepositRequest::where('status', 'pending')->count(),
            'providerBalance' => $providerBalance,
            'lowBalance' => $lowBalance,
            'stuckCount' => Order::where('status', Order::STATUS_STUCK_REVIEW)->count(),
        ]);
    }
}
