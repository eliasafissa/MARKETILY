<?php

namespace App\Enums;

enum TransactionType: string
{
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';
    case Purchase = 'purchase';
    case Refund = 'refund';
    case VipUpgrade = 'vip_upgrade';
    case StoreEarning = 'store_earning';
    case AdminAdjustment = 'admin_adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Deposit => 'Deposit',
            self::Withdrawal => 'Withdrawal',
            self::Purchase => 'Purchase',
            self::Refund => 'Refund',
            self::VipUpgrade => 'VIP Upgrade',
            self::StoreEarning => 'Store Earning',
            self::AdminAdjustment => 'Admin Adjustment',
        };
    }
}
