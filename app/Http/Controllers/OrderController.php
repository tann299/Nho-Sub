<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Requests\OrderPreviewRequest;
use App\Http\Requests\OrderStoreRequest;
use App\Models\Category;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\OrderSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders)
    {
    }

    /** Bước 1: chọn dịch vụ + nhập link/số lượng. */
    public function create(Request $request): View
    {
        $preselected = (int) $request->query('service_id', 0);

        $categories = Category::where('is_active', true)
            ->with(['services' => fn ($q) => $q->where('is_active', true)
                ->select(['id', 'category_id', 'name', 'sell_price', 'min_qty', 'max_qty'])
                ->orderBy('name')])
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Category $c) => $c->services->isNotEmpty());

        return view('panel.order-create', [
            'categories' => $categories,
            'preselected' => $preselected,
            'clientToken' => (string) Str::uuid(),
        ]);
    }

    /** Xem trước tổng tiền — không ghi DB. */
    public function preview(OrderPreviewRequest $request): View
    {
        $preview = $this->orders->preview(
            $request->user()->id,
            (int) $request->input('service_id'),
            (string) $request->input('target_link'),
            (int) $request->input('quantity'),
        );

        return view('panel.order-preview', [
            'preview' => $preview,
            'targetLink' => (string) $request->input('target_link'),
            'clientToken' => (string) $request->input('client_token', Str::uuid()),
        ]);
    }

    /** Bước 2: xác nhận. */
    public function store(OrderStoreRequest $request): RedirectResponse
    {
        try {
            $order = $this->orders->place(
                $request->user()->id,
                (int) $request->input('service_id'),
                (string) $request->input('target_link'),
                (int) $request->input('quantity'),
                (string) $request->input('client_token'),
            );
        } catch (InsufficientBalanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($order->status === Order::STATUS_ERROR) {
            return redirect()->route('panel.orders.show', $order)
                ->with('error', 'Nhà cung cấp không nhận đơn. Tiền đã được hoàn vào ví của bạn.');
        }

        return redirect()->route('panel.orders.show', $order)
            ->with('success', 'Đặt đơn thành công! Đơn #' . $order->id . ' đang được xử lý.');
    }

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $q = trim((string) $request->query('q', ''));

        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->with('service:id,name')
            ->when($status !== '', fn ($x) => $x->where('status', $status))
            ->when($q !== '', fn ($x) => $x->where(function ($w) use ($q) {
                $w->where('id', $q)->orWhere('target_link', 'like', '%' . $q . '%');
            }))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('panel.orders', [
            'orders' => $orders,
            'status' => $status,
            'q' => $q,
        ]);
    }

    public function show(Request $request, int $id): View
    {
        // Chỉ đơn của chính khách — truy vấn theo user_id, không chỉ dựa vào route.
        $order = Order::where('user_id', $request->user()->id)
            ->with(['service:id,name', 'refunds'])
            ->findOrFail($id);

        return view('panel.order-show', ['order' => $order]);
    }

    /** Nút "Cập nhật trạng thái" — rate limit 15 giây/đơn. */
    public function refresh(Request $request, int $id): RedirectResponse
    {
        $order = Order::where('user_id', $request->user()->id)->findOrFail($id);

        $key = 'order-refresh:' . $order->id;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            return back()->with('warning', 'Vui lòng chờ vài giây trước khi cập nhật lại.');
        }

        RateLimiter::hit($key, 15);

        if (! $order->isFinished() && $order->provider_order_id) {
            try {
                app(OrderSyncService::class)->syncOrder($order);
            } catch (\Throwable $e) {
                return back()->with('warning', 'Chưa lấy được trạng thái mới. Vui lòng thử lại sau.');
            }
        }

        return back()->with('success', 'Đã cập nhật trạng thái đơn.');
    }
}
