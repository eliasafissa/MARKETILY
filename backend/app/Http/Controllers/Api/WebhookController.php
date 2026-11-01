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
        Log::info("Payment webhook received: {$gateway}", ['ip' => $request->ip()]);

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

        $outcome = \Illuminate\Support\Facades\DB::transaction(function () use ($result) {
            $transaction = Transaction::where('gateway_ref', $result['reference'])->lockForUpdate()->first();
            if (! $transaction || $transaction->type !== TransactionType::Deposit) return 'not_found';
            if ($transaction->status === TransactionStatus::Approved) return 'already_processed';
            if ($transaction->status !== TransactionStatus::Pending) return 'invalid_status';
            if (abs((float) $transaction->amount - (float) ($result['amount'] ?? 0)) > 0.001) return 'amount_mismatch';
            $user = User::lockForUpdate()->findOrFail($transaction->user_id);
            $transaction->update(['status' => TransactionStatus::Approved]);
            $user->increment('balance', $transaction->amount);
            return 'approved';
        });
        if ($outcome === 'not_found') return response()->json(['error' => 'Deposit not found'], 404);
        if (in_array($outcome, ['amount_mismatch', 'invalid_status'], true)) {
            return response()->json(['error' => $outcome], 422);
        }
        if ($outcome === 'already_processed') return response()->json(['message' => 'Already processed']);

        return response()->json(['message' => 'OK']);
    }
}
