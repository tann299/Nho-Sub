<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Notifications\BalanceAdjusted;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function __construct(private readonly WalletService $wallet)
    {
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $role = (string) $request->query('role', '');

        $users = User::query()
            ->when($q !== '', fn ($x) => $x->where(function ($w) use ($q) {
                $w->where('username', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%")
                  ->orWhere('full_name', 'like', "%{$q}%")
                  ->orWhere('phone', 'like', "%{$q}%");
            }))
            ->when($role !== '', fn ($x) => $x->where('role', $role))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users', ['users' => $users, 'q' => $q, 'role' => $role]);
    }

    public function show(User $user): View
    {
        return view('admin.user-show', [
            'user' => $user,
            'transactions' => $user->walletTransactions()->latest('id')->limit(30)->get(),
            'totalDeposit' => (int) $user->walletTransactions()
                ->where('type', WalletTransaction::TYPE_DEPOSIT)->sum('amount'),
            'orderCount' => $user->orders()->count(),
        ]);
    }

    /** Cộng/trừ tiền thủ công — bắt buộc có lý do, không cho âm. */
    public function adjust(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'min:3', 'max:200'],
        ], [
            'amount.required' => 'Vui lòng nhập số tiền.',
            'amount.not_in' => 'Số tiền phải khác 0.',
            'reason.required' => 'Vui lòng nhập lý do điều chỉnh.',
            'reason.min' => 'Lý do cần ít nhất 3 ký tự.',
        ]);

        try {
            $this->wallet->apply(
                userId: $user->id,
                type: WalletTransaction::TYPE_ADJUST,
                amount: (int) $data['amount'],
                idempotencyKey: 'adjust:' . Str::uuid(),
                note: 'Admin điều chỉnh: ' . $data['reason'],
            );
        } catch (\App\Exceptions\InsufficientBalanceException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        $user->notify(new BalanceAdjusted((int) $data['amount'], $data['reason']));

        return back()->with('success', 'Đã điều chỉnh số dư của ' . $user->username . '.');
    }

    /** Khoá/mở tài khoản — không thể khoá admin cuối cùng. */
    public function toggle(User $user): RedirectResponse
    {
        if ($user->isAdmin() && $user->status === User::STATUS_ACTIVE) {
            $activeAdmins = User::where('role', User::ROLE_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->count();

            if ($activeAdmins <= 1) {
                return back()->withErrors(['user' => 'Không thể khoá quản trị viên cuối cùng.']);
            }
        }

        $user->forceFill([
            'status' => $user->status === User::STATUS_ACTIVE
                ? User::STATUS_LOCKED
                : User::STATUS_ACTIVE,
        ])->save();

        return back()->with('success', $user->status === User::STATUS_ACTIVE
            ? 'Đã mở khoá tài khoản.'
            : 'Đã khoá tài khoản.');
    }
}
