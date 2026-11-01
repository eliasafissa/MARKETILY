<?php

namespace App\Services;

use App\Enums\VipLevel;
use App\Models\Setting;
use App\Models\User;
use App\Enums\OrderStatus;
use App\Events\VipLevelChanged;
use Illuminate\Support\Facades\Log;

class VipService
{
    public function withdrawalLimit(User $user): float
    {
        $key = match ($user->vip_level) {
            VipLevel::Vip1 => 'vip1_withdrawal_limit',
            VipLevel::Vip2 => 'vip2_withdrawal_limit',
            VipLevel::Vip3 => 'vip3_withdrawal_limit',
            VipLevel::Vip4 => 'vip4_withdrawal_limit',
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
            VipLevel::Vip4 => 'vip4_fee_percent',
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

    public function completedSpend(User $user): float
    {
        return round((float) $user->orders()->where('status', OrderStatus::Completed->value)->sum('total'), 2);
    }

    public function earnedLevel(float $completedSpend): VipLevel
    {
        return match (true) {
            $completedSpend >= 5000.0 => VipLevel::Vip3,
            $completedSpend >= 2000.0 => VipLevel::Vip2,
            $completedSpend >= 1000.0 => VipLevel::Vip1,
            default => VipLevel::None,
        };
    }

    /** A manual grant/removal has priority; 'none' explicitly blocks automatic upgrades. */
    public function effectiveLevel(User $user): VipLevel
    {
        return $user->vip_override ?? $this->earnedLevel($this->completedSpend($user));
    }

    public function syncLevel(User $user): VipLevel
    {
        $level = $this->effectiveLevel($user);
        if ($user->vip_level !== $level) {
            $previous = $user->vip_level?->value ?? 'none';
            $user->vip_level = $level;
            $user->save();
            try { event(new VipLevelChanged($user, $previous, $level->value)); }
            catch (\Throwable $e) { Log::warning('VIP notification failed', ['user_id' => $user->id]); }
        }
        return $level;
    }

    public function progress(User $user): array
    {
        $spend = $this->completedSpend($user);
        $next = $spend < 1000 ? 1000 : ($spend < 2000 ? 2000 : ($spend < 5000 ? 5000 : null));
        return [
            'completed_spend' => $spend,
            'next_threshold' => $next,
            'remaining' => $next === null ? 0 : round(max(0, $next - $spend), 2),
            'manual_override' => $user->vip_override?->value,
        ];
    }
}
