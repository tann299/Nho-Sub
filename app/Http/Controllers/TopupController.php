<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\NapxuException;
use App\Http\Requests\StoreTopupOrderRequest;
use App\Models\TikTokAccount;
use App\Models\TopupOrder;
use App\Models\TopupPackage;
use App\Services\Napxu\TopupOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TopupController extends Controller
{
    public function __construct(private readonly TopupOrderService $orders)
    {
    }

    public function create(Request $request): View
    {
        return view('panel.topup', [
            'accounts' => TikTokAccount::where('user_id', $request->user()->id)->orderBy('username')->get(),
            'packages' => TopupPackage::where('is_active', true)->orderBy('coins')->orderBy('sell_price')->get(),
            'clientToken' => (string) Str::uuid(),
        ]);
    }

    public function store(StoreTopupOrderRequest $request): RedirectResponse
    {
        $account = TikTokAccount::where('user_id', $request->user()->id)->findOrFail($request->integer('tiktok_account_id'));
        $package = TopupPackage::where('is_active', true)->findOrFail($request->integer('topup_package_id'));
        try {
            $order = $this->orders->placeOrder($request->user(), $account, $package,
                (string) $request->input('client_token'), $request->filled('pin') ? (string) $request->input('pin') : null);
        } catch (InsufficientBalanceException $e) {
            return back()->with('error', $e->getMessage());
        }
        if ($order->status === TopupOrder::STATUS_FAILED) {
            return redirect()->route('panel.topup.orders')->with('error', 'Napxu từ chối đơn. Tiền đã hoàn về ví NhoSub.');
        }
        if (in_array($order->status, [TopupOrder::STATUS_CHARGED, TopupOrder::STATUS_STUCK_REVIEW], true)) {
            return redirect()->route('panel.topup.orders')->with('warning', 'Đơn đang chờ đối soát. Không gửi lại; tiền chưa hoàn để tránh nạp trùng.');
        }

        return redirect()->route('panel.topup.orders')->with('success', 'Đã nhận đơn nạp xu NX-'.$order->id.'.');
    }

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $orders = TopupOrder::where('user_id', $request->user()->id)->with('account')
            ->when($status !== '', fn ($q) => $q->where('status', $status))->latest('id')->paginate(20)->withQueryString();

        return view('panel.topup-orders', compact('orders', 'status'));
    }

    public function refresh(Request $request, int $id): RedirectResponse
    {
        $order = TopupOrder::where('user_id', $request->user()->id)->findOrFail($id);
        try {
            $this->orders->syncOne($order);
        } catch (NapxuException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return back()->with('success', 'Đã cập nhật trạng thái đơn nạp xu.');
    }

    public function cancel(Request $request, int $id): RedirectResponse
    {
        $order = TopupOrder::where('user_id', $request->user()->id)->findOrFail($id);
        try {
            $confirmed = $this->orders->cancelPending($order);
        } catch (NapxuException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return back()->with($confirmed ? 'success' : 'warning', $confirmed
            ? 'Đã xác nhận hủy và hoàn tiền về ví NhoSub.' : 'Đã gửi yêu cầu hủy; chờ Napxu xác nhận.');
    }
}
