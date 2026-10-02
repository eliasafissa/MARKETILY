<?php

namespace App\Services\Payments;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UsdtGateway implements PaymentGatewayInterface
{
    /**
     * Demo mode is active when the webhook secret is the placeholder string.
     * In demo mode the gateway auto-approves via simulatePayment();
     * in real mode it expects a valid HMAC-SHA256 signature.
     */
    public function isDemoMode(): bool
    {
        return (string) config('services.usdt.secret') === 'placeholder';
    }

    public function name(): string
    {
        return 'usdt';
    }

    public function createDeposit(float $amount, string $currency, array $meta = []): array
    {
        $reference = 'usdt_'.Str::uuid()->toString();
        $wallet = Setting::get('usdt_wallet_address') ?? config('services.usdt.wallet');
        $memo = strtoupper(substr($reference, -8));

        Log::info('USDT deposit created', [
            'reference' => $reference,
            'amount' => $amount,
            'wallet' => $wallet,
            'memo' => $memo,
        ]);

        return [
            'method' => 'usdt',
            'reference' => $reference,
            'amount' => $amount,
            'currency' => 'USDT_BEP20',
            'wallet_address' => $wallet,
            'memo' => $memo,
            'qr_code' => $wallet ? 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data='.urlencode("usdt:{$wallet}?memo={$memo}") : null,
            'instructions' => $wallet
                ? 'Send USDT (BEP-20) to the address above with the memo. Your balance will be credited after 1 confirmation.'
                : 'USDT wallet address not configured. Please contact support.',
        ];
    }

    public function verifyWebhook(Request $request): array
    {
        $signature = $request->header('X-Usdt-Signature');
        $payload = $request->getContent();
        $secret = config('services.usdt.secret');

        $expected = hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expected, (string) $signature)) {
            return [
                'valid' => false,
                'amount' => 0.0,
                'reference' => null,
                'error' => 'Invalid signature',
            ];
        }

        $data = $request->json()->all();

        if (($data['status'] ?? null) !== 'confirmed') {
            return [
                'valid' => false,
                'amount' => 0.0,
                'reference' => null,
                'error' => 'Status is not confirmed',
            ];
        }

        $expected = (float) ($data['expected_amount'] ?? 0);
        $actual = (float) ($data['amount'] ?? 0);
        $tolerance = 0.01;

        if (abs($actual - $expected) > $tolerance) {
            return [
                'valid' => false,
                'amount' => 0.0,
                'reference' => null,
                'error' => 'Amount mismatch',
            ];
        }

        return [
            'valid' => true,
            'amount' => $actual,
            'reference' => $data['reference'] ?? null,
            'error' => null,
        ];
    }

    /**
     * Demo-mode simulator: returns a fake successful USDT transaction.
     * Only succeeds when in demo mode (USDT_WEBHOOK_SECRET == 'placeholder').
     * When real webhook secret is configured this returns failure so the
     * caller knows to wait for a real webhook callback.
     */
    public function simulatePayment(array $meta = []): array
    {
        if (! $this->isDemoMode()) {
            return [
                'success' => false,
                'transaction_id' => null,
                'status' => 'real_mode',
                'method' => 'usdt',
                'error' => 'Real USDT mode active — simulation disabled.',
            ];
        }

        $txid = 'usdt_'.strtoupper(Str::random(16));

        Log::info('USDT simulated payment', [
            'transaction_id' => $txid,
            'wallet_address' => $meta['wallet_address'] ?? null,
            'network' => $meta['network'] ?? 'BEP-20',
            'amount' => $meta['amount'] ?? null,
        ]);

        return [
            'success' => true,
            'transaction_id' => $txid,
            'status' => 'SUCCESS',
            'method' => 'usdt',
            'wallet_address' => $meta['wallet_address'] ?? null,
            'network' => $meta['network'] ?? 'BEP-20',
            'error' => null,
        ];
    }
}
