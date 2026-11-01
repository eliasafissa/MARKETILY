<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.payload' => ['nullable', 'array'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'expected_total' => ['nullable', 'regex:/^(?:0|[1-9][0-9]{0,14})(?:\.[0-9]{1,24})?$/D'],
            'payment_method' => ['required', 'string', 'in:cash_wallet'],
        ];
    }
}
