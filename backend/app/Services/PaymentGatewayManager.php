<?php

namespace App\Services;

use App\Services\Payments\BinancePayGateway;
use App\Services\Payments\CashWalletGateway;
use App\Services\Payments\PaymentGatewayInterface;
use App\Services\Payments\UsdtGateway;
use InvalidArgumentException;

class PaymentGatewayManager
{
    public function driver(string $name): PaymentGatewayInterface
    {
        return match ($name) {
            'binance_pay' => app(BinancePayGateway::class),
            'usdt' => app(UsdtGateway::class),
            'cash_wallet' => app(CashWalletGateway::class),
            default => throw new InvalidArgumentException("Unknown payment gateway: {$name}"),
        };
    }

    public function available(): array
    {
        return [
            'binance_pay' => 'Binance Pay',
            'usdt' => 'USDT (BEP-20)',
            'cash_wallet' => 'Cash Wallet',
        ];
    }
}
