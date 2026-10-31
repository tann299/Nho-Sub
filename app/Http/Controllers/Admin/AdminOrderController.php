<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiSyncLog;
use App\Models\Order;
use App\Services\OrderSyncService;
use App\Services\RefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminOrderController extends Controller
{
    public function __construct(
        private readonly RefundService $refunds,
        private readonly OrderSyncService $sync,
    ) {
    }

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $q = trim((string) $request->query('q', ''));
        $username = trim((string) $request->query('username', ''));

        $orders = Order::query()
            ->with(['service:id,name', 'user:id,username'])
            ->when($status !== '', fn ($x) => $x->where('status', $status))
            ->when($q !== '', fn ($x) => $x->where(function ($w) use ($q) {
                $w->where('id', $q)->orWhere('target_link', 'like', "%{$q}%");
            }))
            ->when($username !== '', fn ($x) => $x->whereHas('user', fn ($u) => $u->where('username', 'like', "%{$username}%")))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.orders', compact('orders', 'status', 'q', 'username'));
    }

    public function show(Order $order): View
    {
        $order->load(['service', 'user', 'refunds']);

        $logs = ApiSyncLog::where('order_id', $order->id)->latest('id')->limit(50)->get();

        return view('admin.order-show', compact('order', 'logs'));
    }

    /** Hoàn tiền thủ công — RefundService tự chống trùng theo seq. */
    public function refund(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:200'],
        ], [
            'reason.required' => 'Vui lòng nhập lý do hoàn tiền.',
        ]);

        $refund = $this->refunds->refundOrder($order, 'admin_manual');

        if ($refund === null) {
            return back()->with('warning', 'Không còn gì để hoàn cho đơn này.');
        }

        return back()->with('success', 'Đã hoàn ' . number_format($refund->amount, 0, ',', '.') . ' ₫ cho khách.');
    }

    /**
     * Xử lý đơn stuck_review:
     *  - action=refund → hoàn tiền + status error
     *  - action=attach → nhập mã provider để tiếp tục đồng bộ
     */
    public function resolveStuck(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:refund,attach'],
            'provider_order_id' => ['nullable', 'string', 'max:128'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        if ($order->status !== Order::STATUS_STUCK_REVIEW) {
            return back()->with('warning', 'Đơn này không ở trạng thái chờ xử lý.');
        }

        if ($data['action'] === 'attach') {
            if (empty($data['provider_order_id'])) {
                return back()->withErrors(['provider_order_id' => 'Vui lòng nhập mã đơn của nhà cung cấp.']);
            }

            $order->forceFill([
                'provider_order_id' => $data['provider_order_id'],
                'status' => Order::STATUS_PROCESSING,
            ])->save();

            return back()->with('success', 'Đã gắn mã nhà cung cấp, đơn tiếp tục đồng bộ.');
        }

        // Chưa gửi lên NCC → hoàn toàn bộ + đóng đơn.
        $order->forceFill([
            'status' => Order::STATUS_ERROR,
            'remains' => (int) $order->quantity,
            'error_message' => $data['reason'] ?? 'Admin xác nhận chưa gửi nhà cung cấp.',
        ])->save();

        $refund = $this->refunds->refundOrder($order->fresh(), 'admin_manual');

        return back()->with('success', $refund
            ? 'Đã hoàn ' . number_format($refund->amount, 0, ',', '.') . ' ₫ và đóng đơn.'
            : 'Đã đóng đơn (không có khoản hoàn).');
    }
}
