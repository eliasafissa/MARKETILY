<?php

namespace App\Services\Payments;

use Illuminate\Http\Request;

/**
 * Internal-only payment flow: an admin manually credits a user's balance.
 * No external API, no webhook — deposits are created in pending state and
 * approved/rejected via the admin dashboard.
 */
class CashWalletGateway implements PaymentGatewayInterface
{
    public function isDemoMode(): bool
    {
        return false; // cash wallet is always real (admin-credited)
    }

    public function name(): string
    {
        return 'cash_wallet';
    }

    public function createDeposit(float $amount, string $currency, array $meta = []): array
    {
        return [
            'method' => 'cash_wallet',
            'amount' => $amount,
            'currency' => $currency,
            'instructions' => 'Your deposit request has been recorded. An admin will credit your account shortly.',
        ];
    }

    public function verifyWebhook(Request $request): array
    {
        return [
            'valid' => false,
            'amount' => 0.0,
            'reference' => null,
            'error' => 'Cash wallet has no webhook; admin must approve manually.',
        ];
    }

    public function simulatePayment(array $meta = []): array
    {
        return [
            'success' => false,
            'transaction_id' => null,
            'status' => 'not_applicable',
            'method' => 'cash_wallet',
            'error' => 'Cash wallet does not use simulation — balance is debited directly.',
        ];
    }
}
