<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1'],
            'wallet_address' => ['required', 'string', 'max:500'],
            'method' => ['required', 'string', 'in:usdt,binance_pay'],
        ];
    }
}
