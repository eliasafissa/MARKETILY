<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CashWallet = 'cash_wallet';
    case BinancePay = 'binance_pay';
    case Usdt = 'usdt';

    public function label(): string
    {
        return match ($this) {
            self::CashWallet => 'Cash Wallet',
            self::BinancePay => 'Binance Pay',
            self::Usdt => 'USDT (BEP-20)',
        };
    }
}
