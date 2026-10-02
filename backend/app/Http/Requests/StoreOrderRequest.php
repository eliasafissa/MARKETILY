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
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.payload' => ['nullable', 'array'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'payment_method' => ['required', 'string', 'in:cash_wallet,binance_pay,usdt'],
            'meta' => ['nullable', 'array'],
            'meta.binance_id' => ['required_if:payment_method,binance_pay', 'nullable', 'string', 'max:255'],
            'meta.binance_email' => ['nullable', 'string', 'email', 'max:255'],
            'meta.wallet_address' => ['required_if:payment_method,usdt', 'nullable', 'string', 'max:255'],
            'meta.tx_hash' => ['nullable', 'string', 'max:255'],
            'meta.network' => ['nullable', 'string', 'max:32'],
        ];
    }
}
