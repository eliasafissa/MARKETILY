<?php

namespace App\Http\Requests;

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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => [
                'required',
                'string',
                'min:10',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'confirmed',
                // At least 1 letter, 1 number, and 1 special character
                'regex:/[A-Za-z]/',
                'regex:' . '/[0-9]/',
                'regex:' . '/[^A-Za-z0-9]/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'password.regex' => 'يجب أن تتضمن كلمة المرور أحرفاً كبيرة وصغيرة وأرقاماً ورمزاً خاصاً.',
            'password.min' => 'يجب أن تكون كلمة المرور من 10 أحرف على الأقل.',
            'name.required' => 'يرجى إدخال الاسم.',
            'email.required' => 'يرجى إدخال البريد الإلكتروني.',
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',
            'email.unique' => 'البريد الإلكتروني مستخدم مسبقاً.',
            'password.required' => 'يرجى إدخال كلمة المرور.',
            'password.confirmed' => 'كلمتا المرور غير متطابقتين.',

        ];
    }
}
