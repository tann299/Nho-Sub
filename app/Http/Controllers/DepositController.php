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

        return view('panel.deposit', [
            'user' => $user,
            'transferContent' => $user->transferContent(),
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
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1000', 'max:1000000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'amount.required' => 'Vui lòng nhập số tiền đã chuyển.',
            'amount.integer' => 'Số tiền phải là số nguyên.',
            'amount.min' => 'Số tiền không hợp lệ.',
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
