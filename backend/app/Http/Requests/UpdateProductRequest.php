<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'name_ar' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category_id' => ['sometimes', 'exists:categories,id'],
            'description' => ['nullable', 'string'],
            'description_ar' => ['sometimes', 'nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0.0001', 'max:1000000000', 'decimal:0,4'],
            'pricing_mode' => ['sometimes', 'in:auto,manual'],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'type' => ['sometimes', 'string', 'in:auto,manual'],
            'is_active' => ['sometimes', 'boolean'],
            'image_base64' => ['nullable', 'string', 'max:7200000'],
            'remove_admin_image' => ['sometimes', 'boolean'],
            'params' => ['sometimes', 'nullable', 'array', 'max:20'],
            'params.*' => ['required', 'string', 'max:150'],
            'icon' => ['nullable', 'string', 'max:20'],
        ];
    }
}
