<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\DepositCurrency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCurrencyController extends Controller
{
    public function update(Request $request, DepositCurrency $currency): JsonResponse
    {
        $validated = $request->validate([
            'syp_per_usd' => ['sometimes', 'required', 'numeric', 'min:1', 'max:100000000'],
            'syriatel_syp_per_usd' => ['sometimes', 'required', 'numeric', 'min:1', 'max:100000000'],
            'sham_cash_usd_receiver' => ['sometimes', 'nullable', 'string', 'max:250'],
        ]);
        if (!$validated) {
            return response()->json(['message' => 'Provide at least one payment setting.'], 422);
        }
        if (array_key_exists('syp_per_usd', $validated)) {
            Setting::set('payment_syp_per_usd', (string) $validated['syp_per_usd'], Setting::GROUP_PAYMENT);
        }
        if (array_key_exists('syriatel_syp_per_usd', $validated)) {
            Setting::set('payment_syriatel_syp_per_usd', (string) $validated['syriatel_syp_per_usd'], Setting::GROUP_PAYMENT);
        }
        if (array_key_exists('sham_cash_usd_receiver', $validated)) {
            $receiver = trim((string) ($validated['sham_cash_usd_receiver'] ?? ''));
            Setting::set('payment_sham_cash_usd_receiver', $receiver, Setting::GROUP_PAYMENT);
        }
        return response()->json([
            'syp_per_usd' => $currency->rate(),
            'syriatel_syp_per_usd' => $currency->rate('syriatel_cash'),
            'message' => 'Payment settings saved; existing deposits keep their original quotes.',
        ]);
    }
}
