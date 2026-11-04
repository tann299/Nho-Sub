<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $stats = [
            'balance' => (int) $user->balance,
            'total_orders' => Order::where('user_id', $user->id)->count(),
            'running_orders' => Order::where('user_id', $user->id)
                ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_CHARGED, Order::STATUS_PROCESSING])
                ->count(),
            // Tổng đã nạp (dòng dương) và đã tiêu (dòng âm) từ sổ ví.
            'total_deposited' => (int) WalletTransaction::where('user_id', $user->id)
                ->where('type', WalletTransaction::TYPE_DEPOSIT)
                ->where('amount', '>', 0)->sum('amount'),
            'total_spent' => (int) abs(
                (int) WalletTransaction::where('user_id', $user->id)
                    ->where('type', WalletTransaction::TYPE_ORDER_CHARGE)
                    ->where('amount', '<', 0)->sum('amount')
            ),
        ];

        $recentOrders = Order::where('user_id', $user->id)
            ->with('service:id,name')
            ->latest('id')
            ->limit(5)
            ->get();

        $notifications = $user->notifications()->latest()->limit(5)->get();

        return view('panel.dashboard', [
            'user' => $user,
            'stats' => $stats,
            'recentOrders' => $recentOrders,
            'notifications' => $notifications,
        ]);
    }
}
