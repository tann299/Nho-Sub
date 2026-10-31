<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WebhookLog;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminWebhookController extends Controller
{
    public function __construct(private readonly WalletService $wallet)
    {
    }

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');

        $logs = WebhookLog::query()
            ->when($status !== '', fn ($x) => $x->where('status', $status))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.webhooks', ['logs' => $logs, 'status' => $status]);
    }

    /** Cộng tay cho giao dịch không khớp — admin đã xác minh sao kê. */
    public function credit(Request $request, WebhookLog $webhook): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:32'],
            'reason' => ['required', 'string', 'min:3', 'max:200'],
        ], [
            'username.required' => 'Vui lòng nhập tên đăng nhập của khách.',
            'reason.required' => 'Vui lòng nhập lý do cộng tiền.',
        ]);

        $user = User::whereRaw('LOWER(username) = ?', [mb_strtolower($data['username'])])->first();

        if (! $user) {
            return back()->withErrors(['username' => 'Không tìm thấy tài khoản.']);
        }

        $this->wallet->apply(
            userId: $user->id,
            type: WalletTransaction::TYPE_DEPOSIT,
            amount: (int) $webhook->amount,
            idempotencyKey: 'webhook-manual:' . $webhook->id,
            note: 'Cộng tay từ webhook #' . $webhook->id . ': ' . $data['reason'],
        );

        $webhook->forceFill([
            'status' => WebhookLog::STATUS_PROCESSED,
            'reason' => 'Admin cộng tay: ' . $data['reason'],
        ])->save();

        return back()->with('success', 'Đã cộng ' . number_format($webhook->amount, 0, ',', '.') . ' ₫ cho ' . $user->username . '.');
    }
}
