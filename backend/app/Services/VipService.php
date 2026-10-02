<?php

namespace App\Services;

use App\Enums\VipLevel;
use App\Models\Setting;
use App\Models\User;

class VipService
{
    public function withdrawalLimit(User $user): float
    {
        $key = match ($user->vip_level) {
            VipLevel::Vip1 => 'vip1_withdrawal_limit',
            VipLevel::Vip2 => 'vip2_withdrawal_limit',
            VipLevel::Vip3 => 'vip3_withdrawal_limit',
            default => null,
        };

        if ($key === null) {
            return 0.0;
        }

        return (float) (Setting::get($key, $user->vip_level->defaultWithdrawalLimit()));
    }

    public function feePercent(User $user): float
    {
        $key = match ($user->vip_level) {
            VipLevel::Vip1 => 'vip1_fee_percent',
            VipLevel::Vip2 => 'vip2_fee_percent',
            VipLevel::Vip3 => 'vip3_fee_percent',
            default => 'regular_fee_percent',
        };

        return (float) (Setting::get($key, $user->vip_level->defaultFeePercent()));
    }

    /**
     * @return array{amount: float, fee: float, net: float, allowed: bool, reason: ?string}
     */
    public function applyWithdrawal(float $amount, User $user): array
    {
        $limit = $this->withdrawalLimit($user);
        $feePct = $this->feePercent($user);
        $fee = round($amount * ($feePct / 100), 2);
        $net = round($amount - $fee, 2);

        $allowed = true;
        $reason = null;

        if ($limit === 0.0) {
            $allowed = false;
            $reason = 'Regular users do not have withdrawal privileges. Upgrade to VIP to withdraw.';
        } elseif ($amount > $limit) {
            $allowed = false;
            $reason = "Amount exceeds your VIP withdrawal limit of {$limit}.";
        }

        if ($amount <= 0) {
            $allowed = false;
            $reason = 'Amount must be greater than zero.';
        }

        if ($net <= 0) {
            $allowed = false;
            $reason = 'Net amount after fees must be greater than zero.';
        }

        return [
            'amount' => $amount,
            'fee' => $fee,
            'net' => $net,
            'allowed' => $allowed,
            'reason' => $reason,
        ];
    }

    public function upgradePrice(VipLevel $target): float
    {
        $key = match ($target) {
            VipLevel::Vip1 => 'vip1_upgrade_price',
            VipLevel::Vip2 => 'vip2_upgrade_price',
            VipLevel::Vip3 => 'vip3_upgrade_price',
            default => null,
        };

        return $key ? (float) Setting::get($key, 0) : 0.0;
    }
}
