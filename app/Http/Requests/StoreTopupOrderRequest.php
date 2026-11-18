<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTopupOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && ! $this->user()->isLocked();
    }

    public function rules(): array
    {
        return [
            'tiktok_account_id' => ['required', 'integer', Rule::exists('tiktok_accounts', 'id')
                ->where('user_id', $this->user()->id)->whereNull('deleted_at')],
            'topup_package_id' => ['required', 'integer', Rule::exists('topup_packages', 'id')->where('is_active', true)],
            'client_token' => ['required', 'uuid'],
            'pin' => ['nullable', 'string', 'regex:/^\d{6}$/D'],
        ];
    }

    public function messages(): array
    {
        return ['tiktok_account_id.exists' => 'Tài khoản TikTok không thuộc bạn hoặc đã gỡ liên kết.',
            'topup_package_id.exists' => 'Gói nạp đã ngừng bán.', 'pin.regex' => 'PIN Napxu phải gồm 6 chữ số.'];
    }
}
