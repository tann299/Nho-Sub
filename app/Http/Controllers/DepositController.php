<?php

namespace App\Http\Controllers;

use App\Models\DepositRequest;
use App\Services\DepositService;
use App\Services\PayOS\PayOSClient;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepositController extends Controller
{
    public function __construct(
        private readonly DepositService $deposits,
        private readonly SettingService $settings,
        private readonly PayOSClient $payos,
    ) {
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        // Lấy yêu cầu nạp đang chờ của khách (duyệt tay) — có mã xác thực.
        $pendingRequest = DepositRequest::where('user_id', $user->id)
            ->where('status', DepositRequest::STATUS_PENDING)
            ->latest('id')
            ->first();

        // Mã xác thực dùng cho chuyển khoản nhanh (không qua yêu cầu duyệt tay):
        // sinh theo phiên — khách chỉ cần kèm mã này trong nội dung CK.
        $transferContent = $user->transferContent();

        return view('panel.deposit', [
            'user' => $user,
            'transferContent' => $transferContent,
            'pendingRequest' => $pendingRequest,
            'minDeposit' => $this->settings->getInt('min_deposit', 10000),
            'bankName' => $this->settings->get('bank_name', ''),
            'bankAccount' => $this->settings->get('bank_account', ''),
            'bankOwner' => $this->settings->get('bank_owner', ''),
            'bankBin' => $this->settings->get('bank_bin', ''),
            'payosEnabled' => $this->payos->isEnabled(),
            'requests' => DepositRequest::where('user_id', $user->id)->latest('id')->limit(20)->get(),
        ]);
    }

    /** Gửi yêu cầu nạp duyệt tay. */
    public function store(Request $request): RedirectResponse
    {
        // min khớp đúng cấu hình min_deposit (tránh thông báo lệch với service).
        $minDeposit = max(1000, $this->settings->getInt('min_deposit', 10000));

        $data = $request->validate([
            'amount' => ['required', 'integer', "min:{$minDeposit}", 'max:1000000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'amount.required' => 'Vui lòng nhập số tiền đã chuyển.',
            'amount.integer' => 'Số tiền phải là số nguyên.',
            'amount.min' => 'Số tiền nạp tối thiểu là ' . number_format($minDeposit, 0, ',', '.') . ' ₫.',
        ]);

        try {
            $this->deposits->createManualRequest(
                $request->user()->id,
                (int) $data['amount'],
                DepositRequest::METHOD_BANK,
                $data['note'] ?? null,
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', 'Đã gửi yêu cầu nạp tiền. Admin sẽ duyệt trong thời gian sớm nhất.');
    }
}
