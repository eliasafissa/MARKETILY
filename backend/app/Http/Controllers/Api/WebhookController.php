<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Events\DepositStatusChanged;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(private readonly PaymentGatewayManager $gateways)
    {
    }

    public function handle(Request $request, string $gateway): JsonResponse
    {
        Log::info("Payment webhook received: {$gateway}", [
            'ip' => $request->ip(),
            'payload' => $request->all(),
        ]);

        try {
            $driver = $this->gateways->driver($gateway);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'Unknown gateway.'], 404);
        }

        $result = $driver->verifyWebhook($request);

        if (! $result['valid']) {
            Log::warning("Payment webhook verification failed: {$gateway}", ['error' => $result['error']]);
            return response()->json(['error' => $result['error'] ?? 'Verification failed.'], 422);
        }

        $transaction = Transaction::where('gateway_ref', $result['reference'])->first();

        if (! $transaction) {
            Log::warning("Payment webhook: transaction not found", ['reference' => $result['reference']]);
            return response()->json(['error' => 'Transaction not found.'], 404);
        }

        if ($transaction->status !== TransactionStatus::Pending) {
            return response()->json(['message' => 'Already processed.']);
        }

        $user = $transaction->user;
        $user->increment('balance', $transaction->amount);
        $transaction->update(['status' => TransactionStatus::Approved]);

        event(new DepositStatusChanged($transaction));

        Log::info("Payment webhook: deposit approved", [
            'transaction_id' => $transaction->id,
            'user_id' => $user->id,
            'amount' => $transaction->amount,
        ]);

        return response()->json(['message' => 'OK']);
    }
}
