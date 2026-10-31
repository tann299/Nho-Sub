<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DepositRequest;
use App\Notifications\DepositApproved;
use App\Services\DepositService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDepositController extends Controller
{
    public function __construct(private readonly DepositService $deposits)
    {
    }

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'pending');

        $requests = DepositRequest::query()
            ->with('user:id,username,email')
            ->when($status !== '', fn ($x) => $x->where('status', $status))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.deposits', [
            'requests' => $requests,
            'status' => $status,
            'pendingCount' => DepositRequest::where('status', 'pending')->count(),
        ]);
    }

    public function approve(Request $request, DepositRequest $deposit): RedirectResponse
    {
        if ($deposit->status !== DepositRequest::STATUS_PENDING) {
            return back()->with('warning', 'Yêu cầu này đã được xử lý.');
        }

        $this->deposits->approveManual($deposit, $request->user()->id, $request->input('note'));

        $deposit->user->notify(new DepositApproved((int) $deposit->amount, (int) $deposit->id));

        return back()->with('success', 'Đã duyệt và cộng ' . number_format($deposit->amount, 0, ',', '.') . ' ₫.');
    }

    public function reject(Request $request, DepositRequest $deposit): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:200'],
        ], [
            'reason.required' => 'Vui lòng nhập lý do từ chối.',
            'reason.min' => 'Lý do cần ít nhất 3 ký tự.',
        ]);

        if ($deposit->status !== DepositRequest::STATUS_PENDING) {
            return back()->with('warning', 'Yêu cầu này đã được xử lý.');
        }

        $this->deposits->rejectManual($deposit, $request->user()->id, $data['reason']);

        return back()->with('success', 'Đã từ chối yêu cầu nạp.');
    }
}
