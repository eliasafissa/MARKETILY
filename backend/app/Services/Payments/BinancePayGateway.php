<?php

namespace App\Services\Payments;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BinancePayGateway implements PaymentGatewayInterface
{
    /**
     * Demo mode is active when the API key is the placeholder string.
     * In demo mode the gateway auto-approves via simulatePayment();
     * in real mode it expects a valid HMAC-SHA512 signature from Binance Pay.
     */
    public function isDemoMode(): bool
    {
        return (string) config('services.binance_pay.key') === 'placeholder';
    }

    public function name(): string
    {
        return 'binance_pay';
    }

    public function createDeposit(float $amount, string $currency, array $meta = []): array
    {
        $reference = 'binance_'.Str::uuid()->toString();

        Log::info('Binance Pay deposit created', [
            'reference' => $reference,
            'amount' => $amount,
            'currency' => $currency,
        ]);

        return [
            'method' => 'binance_pay',
            'reference' => $reference,
            'amount' => $amount,
            'currency' => $currency,
            'qr_code' => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=binance:'.urlencode($reference),
            'checkout_url' => 'https://pay.binance.com/checkout/'.$reference,
            'instructions' => 'Scan the QR code with Binance Pay to complete the payment.',
        ];
    }

    public function verifyWebhook(Request $request): array
    {
        $signature = $request->header('X-Binance-Signature');
        $payload = $request->getContent();
        $secret = config('services.binance_pay.secret');

        $expected = hash_hmac('sha512', $payload, $secret);

        if (! hash_equals($expected, (string) $signature)) {
            return [
                'valid' => false,
                'amount' => 0.0,
                'reference' => null,
                'error' => 'Invalid signature',
            ];
        }

        $data = $request->json()->all();

        if (($data['status'] ?? null) !== 'PAID') {
            return [
                'valid' => false,
                'amount' => 0.0,
                'reference' => null,
                'error' => 'Status is not PAID',
            ];
        }

        return [
            'valid' => true,
            'amount' => (float) ($data['amount'] ?? 0),
            'reference' => $data['reference'] ?? null,
            'error' => null,
        ];
    }

    /**
     * Demo-mode simulator: returns a fake successful Binance Pay transaction.
     * Only succeeds when in demo mode (BINANCE_PAY_KEY == 'placeholder').
     * When real keys are configured this returns failure so the caller
     * knows to wait for a real webhook callback instead.
     */
    public function simulatePayment(array $meta = []): array
    {
        if (! $this->isDemoMode()) {
            return [
                'success' => false,
                'transaction_id' => null,
                'status' => 'real_mode',
                'method' => 'binance_pay',
                'error' => 'Real Binance Pay mode active — simulation disabled.',
            ];
        }

        $txid = 'binance_'.strtoupper(Str::random(12));

        Log::info('Binance Pay simulated payment', [
            'transaction_id' => $txid,
            'account' => $meta['binance_id'] ?? $meta['binance_email'] ?? null,
            'amount' => $meta['amount'] ?? null,
        ]);

        return [
            'success' => true,
            'transaction_id' => $txid,
            'status' => 'SUCCESS',
            'method' => 'binance_pay',
            'account' => $meta['binance_id'] ?? $meta['binance_email'] ?? null,
            'error' => null,
        ];
    }
}
