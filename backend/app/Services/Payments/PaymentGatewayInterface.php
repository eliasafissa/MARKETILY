<?php

namespace App\Services\Payments;

use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    /** Returns true when the gateway is in demo/placeholder mode (no real API keys configured). */
    public function isDemoMode(): bool;

    public function name(): string;

    /**
     * @return array<string, mixed> Data required by the frontend to complete the deposit (QR, address, etc.)
     */
    public function createDeposit(float $amount, string $currency, array $meta = []): array;

    /**
     * Simulate a successful payment — used in demo mode to give the user a
     * fake transaction id without touching the user's internal balance.
     *
     * @param  array<string, mixed>  $meta  user-supplied details (Binance ID, USDT address, etc.)
     * @return array{success: bool, transaction_id: string, status: string, method: string}
     */
    public function simulatePayment(array $meta = []): array;

    /**
     * @return array{valid: bool, amount: float, reference: ?string, error: ?string}
     */
    public function verifyWebhook(Request $request): array;
}
