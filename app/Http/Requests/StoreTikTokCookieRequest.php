<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTikTokCookieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && ! $this->user()->isLocked();
    }

    public function rules(): array
    {
        return ['cookie' => ['required', 'string', 'max:20000'], 'consent' => ['accepted']];
    }

    public function messages(): array
    {
        return ['cookie.required' => 'Vui lòng nhập cookie TikTok.', 'cookie.max' => 'Cookie quá dài.',
            'consent.accepted' => 'Cần xác nhận quyền sở hữu và việc gửi cookie tới Napxu.'];
    }
}
