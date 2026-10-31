<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => [
                'required', 'string', 'min:3', 'max:32', 'regex:/^[A-Za-z0-9_]+$/',
                // Không phân biệt hoa thường khi kiểm tra trùng: KHACHVIP == khachvip.
                function (string $attribute, mixed $value, \Closure $fail) {
                    $exists = User::whereRaw('LOWER(username) = ?', [mb_strtolower((string) $value)])->exists();
                    if ($exists) {
                        $fail('Tên đăng nhập đã tồn tại.');
                    }
                },
            ],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'full_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\s.-]{8,20}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => 'Vui lòng nhập tên đăng nhập.',
            'username.min' => 'Tên đăng nhập phải có ít nhất 3 ký tự.',
            'username.max' => 'Tên đăng nhập không quá 32 ký tự.',
            'username.regex' => 'Tên đăng nhập chỉ gồm chữ, số và dấu gạch dưới.',
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'email.unique' => 'Email đã được sử dụng.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'password.confirmed' => 'Mật khẩu nhập lại không khớp.',
            'phone.regex' => 'Số điện thoại không hợp lệ.',
        ];
    }
}
