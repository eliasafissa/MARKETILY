<?php

namespace App\Services;

use App\Services\Payments\ManualTransferGateway;
use App\Services\Payments\PaymentGatewayInterface;
use App\Models\Setting;
use InvalidArgumentException;

class PaymentGatewayManager
{
    public const METHODS = [
        'sham_cash' => 'Sham Cash (SYP)',
        'sham_cash_usd' => 'Sham Cash (USD)',
        'syriatel_cash' => 'Syriatel Cash',
        'c_wallet' => 'C Wallet',
        'binance_pay' => 'Binance Pay (manual)',
        'usdt' => 'USDT BEP-20 (manual review)',
    ];

    public function driver(string $name): PaymentGatewayInterface
    {
        if (!array_key_exists($name, self::METHODS)) throw new InvalidArgumentException('Unknown payment gateway.');
        return new ManualTransferGateway($name);
    }

    public function available(): array { return self::METHODS; }

    public function publicMethods(): array
    {
        $result = [];
        $keys = [
            'sham_cash' => 'sham_cash_receiver',
            'sham_cash_usd' => 'sham_cash_usd_receiver',
            'syriatel_cash' => 'syriatel_cash_receiver',
            'c_wallet' => 'c_wallet_receiver',
            'binance_pay' => 'binance_pay_receiver',
            'usdt' => 'usdt_receiver',
        ];

        foreach (self::METHODS as $id => $label) {
            $settingKey = $keys[$id] ?? ($id . '_receiver');
            $receiver = trim((string) Setting::get($settingKey, ''));
            
            if ($id === 'sham_cash_usd' && $receiver === '') {
                $receiver = trim((string) Setting::get('payment_sham_cash_usd_receiver', ''));
            }

            $rate = app(DepositCurrency::class)->rate($id);
            $syrian = in_array($id, ['sham_cash', 'syriatel_cash'], true);
            
            $receiverValid = $receiver !== '' && !str_contains(strtoupper($receiver), 'MOCK_');
            if ($id === 'usdt') {
                $receiverValid = $receiverValid && (bool) preg_match('/^0x[a-fA-F0-9]{40}$/', $receiver);
            }
            
            $disabledReason = !$receiverValid
                ? 'بيانات محفظة الاستقبال مفقودة أو تجريبية؛ تأكد من رقم الحساب الفعلي.'
                : ($syrian && $rate === null ? 'يجب أن يحدد الأدمن سعر صرف الدولار من لوحة الإعدادات أولاً.' : null);
                
            $result[] = [
                'id' => $id, 
                'label' => $label, 
                'enabled' => $receiverValid && (!$syrian || $rate !== null),
                'receiver' => $receiver ?: null, 
                'disabled_reason' => $disabledReason,
                'other_receivers' => $id === 'syriatel_cash' ? array_values(array_filter([(string) config('services.manual_payments.syriatel_cash_alt', '')])) : [],
                'currency' => $syrian ? 'SYP' : 'USD', 
                'syp_per_usd' => $rate,
                'minimum_transfer' => $id === 'sham_cash' ? 100 : ($id === 'syriatel_cash' ? 50 : 0),
                'network' => $id === 'usdt' ? 'BSC / BEP-20' : ($id === 'c_wallet' ? 'Confirm token and chain with merchant' : null),
                'verification' => 'admin_review',
                'notice' => in_array($id, ['sham_cash', 'sham_cash_usd'], true) ? 'ممنوع التحويل من حساب مخفي أو طرف ثالث، ممنوع كتابة ملاحظات، اسم المرسل الثلاثي يجب أن يطابق الاسم المسجل بالموقع. المخالفة قد تؤدي لرفض الإيداع أو حظر الحساب.' : null,
            ];
        }
        return $result;
    }
}