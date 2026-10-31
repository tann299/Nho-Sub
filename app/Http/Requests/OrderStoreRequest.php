<?php

namespace App\Http\Requests;

class OrderStoreRequest extends OrderPreviewRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'client_token' => ['required', 'uuid'],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'client_token.required' => 'Phiên đặt đơn không hợp lệ, vui lòng thử lại.',
            'client_token.uuid' => 'Phiên đặt đơn không hợp lệ, vui lòng thử lại.',
        ]);
    }
}
