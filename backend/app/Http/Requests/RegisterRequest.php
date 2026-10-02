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
            'password.regex' => 'Password must contain letters, numbers, and a special character.',
            'password.min' => 'Password must be at least 10 characters long.',
        ];
    }
}
