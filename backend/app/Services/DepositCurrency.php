<?php

namespace App\Services;

use App\Models\Setting;

/** Wallet ledger is USD; SYP is an exchange-rate-locked display/payment currency. */
class DepositCurrency
{
    public function rate(?string $method = null): ?float
    {
        // Syriatel uses its own admin-defined SYP per USD conversion; never
        // silently substitute the general rate for a missing Syriatel quote.
        $key = $method === 'syriatel_cash' ? 'payment_syriatel_syp_per_usd' : 'payment_syp_per_usd';
        $raw = Setting::get($key);
        if ($raw === null || !is_numeric($raw)) return null;
        $rate = (float) $raw;
        return is_finite($rate) && $rate >= 1 && $rate <= 100000000 ? $rate : null;
    }

    public function quote(float $input, string $currency, ?string $method = null): array
    {
        if (!is_finite($input) || $input <= 0 || $input > 1000000000) {
            throw new \DomainException('Invalid deposit amount.');
        }
        $rate = $this->rate($method);
        if ($currency === 'SYP') {
            if (!$rate) throw new \DomainException('SYP exchange rate is not configured by admin.');
            $usd = round($input / $rate, 2);
            if ($usd < 0.01) throw new \DomainException('Amount is too small at the current exchange rate.');
            return ['amount_usd' => $usd, 'amount_syp' => round($usd * $rate, 0), 'rate' => $rate];
        }
        if ($currency !== 'USD') throw new \DomainException('Unsupported currency.');
        return ['amount_usd' => round($input, 2), 'amount_syp' => $rate ? round(round($input, 2) * $rate, 0) : null, 'rate' => $rate];
    }
}
