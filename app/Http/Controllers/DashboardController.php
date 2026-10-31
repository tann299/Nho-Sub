<?php

namespace App\Http\Controllers;

use App\Models\Order;
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
