<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\NapxuException;
use App\Http\Controllers\Controller;
use App\Models\TopupOrder;
use App\Services\Napxu\TopupOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminTopupOrderController extends Controller
{
    public function __construct(private readonly TopupOrderService $orders)
    {
    }

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $orders = TopupOrder::with(['user:id,username', 'account'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->latest('id')->paginate(20)->withQueryString();

        return view('admin.topup-orders', compact('orders', 'status'));
    }

    public function refund(Request $request, TopupOrder $order): RedirectResponse
    {
        $request->validate(['confirmed' => ['required', 'accepted']]);
        $this->orders->manualRefund($order);

        return back()->with('success', 'Đã hoàn tiền nạp xu. Khóa giao dịch chặn hoàn trùng.');
    }

    public function reconcile(Request $request, TopupOrder $order): RedirectResponse
    {
        $data = $request->validate(['napxu_order_id' => ['required', 'integer', 'min:1'], 'confirmed' => ['required', 'accepted']]);
        try {
            $this->orders->reconcile($order, (int) $data['napxu_order_id']);
        } catch (NapxuException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã gắn biên nhận Napxu và cập nhật trạng thái; không gửi lại đơn.');
    }

    public function refresh(TopupOrder $order): RedirectResponse
    {
        try {
            $this->orders->syncOne($order);
        } catch (NapxuException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return back()->with('success', 'Đã cập nhật đơn nạp xu.');
    }
}
