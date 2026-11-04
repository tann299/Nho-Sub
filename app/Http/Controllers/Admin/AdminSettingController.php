<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminSettingController extends Controller
{
    public function __construct(private readonly SettingService $settings)
    {
    }

    public function edit(): View
    {
        return view('admin.settings', [
            'exchangeRate' => $this->settings->getInt('exchange_rate', 26000),
            'markupPercent' => $this->settings->getDecimal('default_markup_percent', '30'),
            'minDeposit' => $this->settings->getInt('min_deposit', 10000),
            'bankName' => $this->settings->get('bank_name', ''),
            'bankAccount' => $this->settings->get('bank_account', ''),
            'bankOwner' => $this->settings->get('bank_owner', ''),
            'bankBin' => $this->settings->get('bank_bin', ''),
            'providerAlert' => $this->settings->get('provider_balance_alert', '100'),
            'demoMode' => $this->settings->get('demo_mode', 'auto'),
            'demoScenario' => $this->settings->get('demo_scenario', 'auto'),
            'pinnedIp' => $this->settings->get('topsubre_pinned_ip', ''),
            'sepayEnabled' => $this->settings->getBool('sepay_enabled', true),
            'payosEnabled' => $this->settings->getBool('payos_enabled', false),
            'masked' => [
                'topsubre_api_key' => $this->settings->maskedSecret('topsubre_api_key'),
                'sepay_api_key' => $this->settings->maskedSecret('sepay_api_key'),
                'payos_client_id' => $this->settings->maskedSecret('payos_client_id'),
                'payos_api_key' => $this->settings->maskedSecret('payos_api_key'),
                'payos_checksum_key' => $this->settings->maskedSecret('payos_checksum_key'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'exchange_rate' => ['required', 'integer', 'min:1', 'max:1000000'],
            'default_markup_percent' => ['required', 'numeric', 'min:0', 'max:1000'],
            'min_deposit' => ['required', 'integer', 'min:1000', 'max:1000000000'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account' => ['nullable', 'string', 'max:40'],
            'bank_owner' => ['nullable', 'string', 'max:120'],
            'bank_bin' => ['nullable', 'string', 'max:12'],
            'provider_balance_alert' => ['nullable', 'numeric', 'min:0'],
            // Không bắt buộc: cho phép lưu một phần mà không xoá cấu hình còn lại.
            'demo_mode' => ['nullable', 'in:auto,on,off'],
            'demo_scenario' => ['nullable', 'in:auto,partial,cancel,fail'],
            // IP ghim để vượt DNS bị lọc (để trống = dùng DNS thường).
            'topsubre_pinned_ip' => ['nullable', 'ip'],
            'sepay_enabled' => ['nullable', 'boolean'],
            'payos_enabled' => ['nullable', 'boolean'],
            // Key bí mật: để trống = giữ nguyên key cũ.
            'topsubre_api_key' => ['nullable', 'string', 'max:255'],
            'sepay_api_key' => ['nullable', 'string', 'max:255'],
            'payos_client_id' => ['nullable', 'string', 'max:255'],
            'payos_api_key' => ['nullable', 'string', 'max:255'],
            'payos_checksum_key' => ['nullable', 'string', 'max:255'],
        ], [
            'exchange_rate.required' => 'Vui lòng nhập tỉ giá.',
            'default_markup_percent.required' => 'Vui lòng nhập % lợi nhuận.',
            'min_deposit.min' => 'Mức nạp tối thiểu không hợp lệ.',
        ]);

        // Giá trị thường — CHỈ ghi khi form thực sự gửi lên.
        // Ghi null cho key vắng mặt sẽ xoá cấu hình hiện có (lỗi rất khó phát hiện).
        foreach ([
            'exchange_rate', 'default_markup_percent', 'min_deposit', 'bank_name',
            'bank_account', 'bank_owner', 'bank_bin', 'provider_balance_alert',
            'demo_mode', 'demo_scenario', 'topsubre_pinned_ip',
        ] as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null) {
                $this->settings->set($key, $data[$key]);
            }
        }

        $this->settings->set('sepay_enabled', $request->boolean('sepay_enabled') ? '1' : '0');
        $this->settings->set('payos_enabled', $request->boolean('payos_enabled') ? '1' : '0');

        // Key bí mật: chỉ ghi khi có giá trị mới.
        foreach (['topsubre_api_key', 'sepay_api_key', 'payos_client_id', 'payos_api_key', 'payos_checksum_key'] as $key) {
            $value = trim((string) ($data[$key] ?? ''));
            if ($value !== '') {
                $this->settings->setSecret($key, $value);
            }
        }

        $this->settings->forgetCache();

        return back()->with('success', 'Đã lưu cấu hình.');
    }
}
