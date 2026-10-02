<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
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
            'type' => ['sometimes', 'string', 'in:auto,manual'],
            'description' => ['nullable', 'string'],
            'description_ar' => ['sometimes', 'nullable', 'string'],
            'icon' => ['nullable', 'string'],
            'image_base64' => ['nullable', 'string'],
            'sort_order' => ['sometimes', 'integer'],
        ];
    }
}
