<?php

namespace App\Services\Payments;

use App\Services\DepositCurrency;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Setting;

/** All five deposit methods await independent operator reconciliation. */
class ManualTransferGateway implements PaymentGatewayInterface
{
    public function __construct(private readonly string $method) {}
    public function isDemoMode(): bool { return false; }
    public function name(): string { return $this->method; }

    public function createDeposit(float $amount, string $currency, array $meta = []): array
    {
        $keys = [
            'sham_cash' => 'sham_cash_receiver',
            'sham_cash_usd' => 'sham_cash_usd_receiver',
            'syriatel_cash' => 'syriatel_cash_receiver',
            'c_wallet' => 'c_wallet_receiver',
            'binance_pay' => 'binance_pay_receiver',
            'usdt' => 'usdt_receiver',
        ];
        
        $settingKey = $keys[$this->method] ?? ($this->method . '_receiver');
        $destination = trim((string) Setting::get($settingKey, ''));

        // Fallback for legacy Sham Cash USD key
        if ($this->method === 'sham_cash_usd' && $destination === '') {
            $destination = trim((string) Setting::get('payment_sham_cash_usd_receiver', ''));
        }

        if ($destination === '' || str_contains(strtoupper($destination), 'MOCK_') ||
            ($this->method === 'usdt' && !preg_match('/^0x[a-fA-F0-9]{40}$/', $destination))) {
            throw new \DomainException('A real receiving account must be configured before creating a deposit.');
        }
        
        $rate = app(DepositCurrency::class)->rate($this->method);
        $isSyrian = in_array($this->method, ['sham_cash', 'syriatel_cash'], true);
        
        if ($isSyrian && !$rate) throw new \DomainException('SYP exchange rate is not configured.');
        
        $transferAmount = $isSyrian ? round($amount * $rate, 0) : round($amount, 2);
        $minimum = $this->method === 'sham_cash' ? 100 : ($this->method === 'syriatel_cash' ? 50 : 0);
        
        if ($isSyrian && $transferAmount < $minimum) {
            throw new \DomainException('Minimum for this payment method is '.$minimum.' SYP.');
        }
        
        return [
            'method' => $this->method,
            'reference' => 'HS-'.strtoupper(Str::random(12)),
            'amount' => round($amount, 2), 
            'currency' => 'USD',
            'transfer_amount' => $transferAmount,
            'transfer_currency' => $isSyrian ? 'SYP' : 'USD',
            'rate_snapshot' => $rate,
            'wallet_address' => $destination,
            'network' => $this->method === 'usdt' ? 'BSC / BEP-20' : null,
            'verification' => 'admin_review',
            'notice' => in_array($this->method, ['sham_cash', 'sham_cash_usd'], true) ? 'ممنوع التحويل من حساب مخفي، بدون ملاحظات، من اسم ثلاثي حقيقي مطابق لحساب الموقع، وممنوع التحويل من طرف ثالث.' : null,
        ];
    }
    public function verifyWebhook(Request $request): array { return ['valid' => false, 'amount' => 0, 'reference' => null]; }
    public function simulatePayment(array $meta = []): array { return ['success' => false, 'status' => 'disabled']; }
}