<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'method' => ['required', 'string', 'in:sham_cash,sham_cash_usd,syriatel_cash,binance_pay,usdt'],
            'payment_reference' => ['required', 'string', 'min:5', 'max:190'],
            'sender_account' => ['required', 'string', 'min:3', 'max:190'],
            'sender_name' => ['nullable', 'string', 'max:150'],
        ];
    }
}
